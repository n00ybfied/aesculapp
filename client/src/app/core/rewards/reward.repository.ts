import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { RewardCatalogService } from './reward-catalog.service';

export interface Reward {
  readonly id: string;
  readonly title: string;
  readonly subtitle: string;
  readonly description: string;
  readonly imageUrl?: string;
  readonly requiredPoints: number;
}

export interface PointsHistoryItem {
  readonly id: string;
  readonly label: string;
  readonly dateLabel: string;
  readonly points: number;
}

export interface PointsHistoryPage {
  readonly transactions: readonly PointsHistoryItem[];
  readonly page: number;
  readonly totalPages: number;
  readonly total: number;
}

export interface RewardsOverview {
  readonly availablePoints: number;
  readonly rewards: readonly Reward[];
  readonly history: readonly PointsHistoryItem[];
  readonly activeRedemption: ActiveRedemption | null;
}

export interface ActiveRedemption {
  readonly id: string;
  readonly items: readonly ActiveRedemptionItem[];
  readonly totalPoints: number;
  readonly validUntil: number;
}

export interface ActiveRedemptionItem {
  readonly rewardId: string;
  readonly title: string;
  readonly quantity: number;
  readonly pointsPerItem: number;
}

export interface RewardSelection {
  readonly rewardId: string;
  readonly quantity: number;
}

export interface RewardRedemption {
  readonly remainingPoints: number;
  readonly activeRedemption: ActiveRedemption;
}

interface PersistedRewardState {
  readonly availablePoints: number;
  readonly history: readonly PointsHistoryItem[];
  readonly activeRedemption: ActiveRedemption | null;
}

export const rewardStorageKey = 'aesculapp.mock-rewards.v1';

const createInitialState = (): PersistedRewardState => ({
  availablePoints: 1_230,
  activeRedemption: null,
  history: [],
});

export abstract class RewardRepository {
  abstract getOverview(): Promise<RewardsOverview>;
  abstract redeem(selections: readonly RewardSelection[]): Promise<RewardRedemption>;
  abstract credit(points: number, label: string): Promise<void>;
  abstract reset(): Promise<void>;
  abstract getActiveRedemption(): Promise<ActiveRedemption | null>;
  abstract getHistory(page?: number): Promise<PointsHistoryPage>;
}

@Injectable()
export class MockRewardRepository extends RewardRepository {
  private readonly catalog = inject(RewardCatalogService);
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);
  private state = this.readState();
  private currentRewards: readonly Reward[] = [];
  private readonly rewards: readonly Reward[] = [
    { id: 'tea', title: 'Tee-Genuss', subtitle: 'Wohlfühltee', description: 'Eine Packung Wohlfühltee Ihrer Wahl.', requiredPoints: 500 },
    { id: 'voucher', title: '5 € Gutschein', subtitle: 'Für Ihren Einkauf', description: 'Einlösbar bei Ihrem nächsten Einkauf.', requiredPoints: 1_000 },
    { id: 'care', title: 'Pflege-Set', subtitle: 'Für jeden Tag', description: 'Praktische Auswahl für Ihre tägliche Pflege.', requiredPoints: 1_500 },
  ];
  async getOverview(): Promise<RewardsOverview> {
    await this.getActiveRedemption();
    await this.refreshServerBalance();
    const history = await this.getHistory();
    try {
      this.currentRewards = await this.catalog.getVisibleRewards();
    } catch {
      this.currentRewards = this.rewards;
    }
    this.updateState({ ...this.state, history: history.transactions });
    return this.createOverview(this.currentRewards);
  }

  async redeem(selections: readonly RewardSelection[]): Promise<RewardRedemption> {
    const items = this.createRedemptionItems(selections);
    const totalPoints = items.reduce((total, item) => total + item.pointsPerItem * item.quantity, 0);

    if (items.length === 0 || totalPoints > this.state.availablePoints) {
      throw new Error('Reward cannot be redeemed.');
    }

    const response = await firstValueFrom(this.http.post<{ remainingPoints: number; redemption: { id: number; validUntil: string } }>(
      `${this.apiBaseUrl}/rewards/redeem`,
      { selections },
      { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) },
    ));
    const previousRedemption = await this.getActiveRedemption();
    const activeRedemption: ActiveRedemption = {
      id: String(response.redemption.id),
      items: this.mergeRedemptionItems(previousRedemption?.items ?? [], items),
      totalPoints: (previousRedemption?.totalPoints ?? 0) + totalPoints,
      validUntil: new Date(response.redemption.validUntil).getTime(),
    };

    this.updateState({
      availablePoints: response.remainingPoints,
      history: [
        ...items.map((item) => ({
          id: `reward-${item.rewardId}-${Date.now()}-${item.quantity}`,
          label: item.quantity > 1 ? `${item.quantity}× ${item.title}` : item.title,
          dateLabel: 'Heute',
          points: -(item.pointsPerItem * item.quantity),
        })),
        ...this.state.history,
      ],
      activeRedemption,
    });

    return { remainingPoints: this.state.availablePoints, activeRedemption };
  }

  async credit(points: number, label: string): Promise<void> {
    if (points <= 0) {
      throw new Error('Points must be positive.');
    }

    this.updateState({
      availablePoints: this.state.availablePoints + points,
      history: [{ id: `credit-${Date.now()}`, label, dateLabel: 'Heute', points }, ...this.state.history],
      activeRedemption: this.state.activeRedemption,
    });
  }

  async reset(): Promise<void> {
    this.updateState(createInitialState());
  }

  async getActiveRedemption(): Promise<ActiveRedemption | null> {
    try {
      const response = await firstValueFrom(this.http.get<{ redemption: { id: number } | null }>(
        `${this.apiBaseUrl}/rewards/active`,
        { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) },
      ));
      if (response.redemption === null) {
        this.updateState({ ...this.state, activeRedemption: null });
        return null;
      }
      if (this.state.activeRedemption?.id !== String(response.redemption.id)) {
        return null;
      }
    } catch {
      // The local prototype fallback remains usable while the API is unavailable.
    }
    const activeRedemption = this.state.activeRedemption;
    if (activeRedemption && activeRedemption.validUntil <= Date.now()) {
      this.updateState({ ...this.state, activeRedemption: null });
      return null;
    }

    return activeRedemption;
  }

  async getHistory(page = 1): Promise<PointsHistoryPage> {
    const token = this.auth.accessToken();
    if (token === null) {
      return { transactions: [], page: 1, totalPages: 0, total: 0 };
    }

    try {
      const response = await firstValueFrom(this.http.get<{
        transactions: Array<{ id: number; label: string; points: number; createdAt: string }>;
        page: number;
        totalPages: number;
        total: number;
      }>(
        this.apiBaseUrl + '/rewards/transactions',
        { params: { page: String(page), pageSize: '10' }, headers: new HttpHeaders({ Authorization: 'Bearer ' + token }) },
      ));
      return {
        transactions: response.transactions.map((transaction) => ({
          id: String(transaction.id),
          label: transaction.label,
          points: transaction.points,
          dateLabel: new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(transaction.createdAt)),
        })),
        page: response.page,
        totalPages: response.totalPages,
        total: response.total,
      };
    } catch {
      return { transactions: [], page: 1, totalPages: 0, total: 0 };
    }
  }

  private readState(): PersistedRewardState {
    try {
      const savedState: unknown = JSON.parse(localStorage.getItem(rewardStorageKey) ?? 'null');
      if (this.isPersistedRewardState(savedState)) {
        return savedState;
      }
    } catch {
      // Local Storage is optional for the prototype; an in-memory state remains available.
    }

    return createInitialState();
  }

  private updateState(state: PersistedRewardState): void {
    this.state = state;

    try {
      localStorage.setItem(rewardStorageKey, JSON.stringify(state));
    } catch {
      // Local Storage is optional for the prototype; an in-memory state remains available.
    }
  }

  private isPersistedRewardState(value: unknown): value is PersistedRewardState {
    return typeof value === 'object'
      && value !== null
      && 'availablePoints' in value
      && typeof value.availablePoints === 'number'
      && 'history' in value
      && Array.isArray(value.history)
      && 'activeRedemption' in value
      && (value.activeRedemption === null
        || (typeof value.activeRedemption === 'object'
          && value.activeRedemption !== null
          && 'items' in value.activeRedemption
          && Array.isArray(value.activeRedemption.items)));
  }

  private createRedemptionItems(selections: readonly RewardSelection[]): readonly ActiveRedemptionItem[] {
    const quantities = new Map<string, number>();

    for (const selection of selections) {
      if (!Number.isInteger(selection.quantity) || selection.quantity <= 0) {
        return [];
      }

      quantities.set(selection.rewardId, (quantities.get(selection.rewardId) ?? 0) + selection.quantity);
    }

    const items: ActiveRedemptionItem[] = [];
    for (const [rewardId, quantity] of quantities) {
      const reward = this.currentRewards.find((item) => item.id === rewardId);
      if (!reward) {
        return [];
      }

      items.push({ rewardId, title: reward.title, quantity, pointsPerItem: reward.requiredPoints });
    }

    return items;
  }

  private mergeRedemptionItems(
    existingItems: readonly ActiveRedemptionItem[],
    newItems: readonly ActiveRedemptionItem[],
  ): readonly ActiveRedemptionItem[] {
    const merged = new Map(existingItems.map((item) => [item.rewardId, item]));

    for (const item of newItems) {
      const existing = merged.get(item.rewardId);
      merged.set(item.rewardId, existing ? { ...item, quantity: existing.quantity + item.quantity } : item);
    }

    return [...merged.values()];
  }

  private createOverview(rewards: readonly Reward[] = this.rewards): RewardsOverview {
    return {
      availablePoints: this.state.availablePoints,
      rewards,
      history: this.state.history,
      activeRedemption: this.state.activeRedemption,
    };
  }

  private async refreshServerBalance(): Promise<void> {
    const token = this.auth.accessToken();
    if (token === null) {
      return;
    }

    try {
      const response = await firstValueFrom(this.http.get<{ availablePoints: number }>(
        this.apiBaseUrl + '/rewards/balance',
        { headers: new HttpHeaders({ Authorization: 'Bearer ' + token }) },
      ));
      this.updateState({ ...this.state, availablePoints: response.availablePoints });
    } catch {
      // The local fallback is retained only while the API is unavailable.
    }
  }
}
