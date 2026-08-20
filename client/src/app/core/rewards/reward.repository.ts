import { Injectable } from '@angular/core';

export interface Reward {
  readonly id: string;
  readonly title: string;
  readonly description: string;
  readonly requiredPoints: number;
}

export interface PointsHistoryItem {
  readonly id: string;
  readonly label: string;
  readonly dateLabel: string;
  readonly points: number;
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
  history: [
    { id: 'receipt-1', label: 'Einkauf in der Stadtapotheke', dateLabel: 'Heute', points: 42 },
    { id: 'receipt-2', label: 'Einkauf in der Stadtapotheke', dateLabel: '12. August', points: 35 },
    { id: 'bonus-1', label: 'Willkommensbonus', dateLabel: '1. August', points: 100 },
  ],
});

export abstract class RewardRepository {
  abstract getOverview(): Promise<RewardsOverview>;
  abstract redeem(selections: readonly RewardSelection[]): Promise<RewardRedemption>;
  abstract credit(points: number, label: string): Promise<void>;
  abstract reset(): Promise<void>;
  abstract getActiveRedemption(): Promise<ActiveRedemption | null>;
}

@Injectable()
export class MockRewardRepository extends RewardRepository {
  private state = this.readState();
  private readonly rewards: readonly Reward[] = [
    { id: 'tea', title: 'Tee-Genuss', description: 'Eine Packung Wohlfühltee Ihrer Wahl.', requiredPoints: 500 },
    { id: 'voucher', title: '5 € Gutschein', description: 'Einlösbar bei Ihrem nächsten Einkauf.', requiredPoints: 1_000 },
    { id: 'care', title: 'Pflege-Set', description: 'Praktische Auswahl für Ihre tägliche Pflege.', requiredPoints: 1_500 },
  ];
  async getOverview(): Promise<RewardsOverview> {
    await this.getActiveRedemption();
    return this.createOverview();
  }

  async redeem(selections: readonly RewardSelection[]): Promise<RewardRedemption> {
    const items = this.createRedemptionItems(selections);
    const totalPoints = items.reduce((total, item) => total + item.pointsPerItem * item.quantity, 0);

    if (items.length === 0 || totalPoints > this.state.availablePoints) {
      throw new Error('Reward cannot be redeemed.');
    }

    const previousRedemption = await this.getActiveRedemption();
    const activeRedemption: ActiveRedemption = {
      id: previousRedemption?.id ?? `redemption-${Date.now()}`,
      items: this.mergeRedemptionItems(previousRedemption?.items ?? [], items),
      totalPoints: (previousRedemption?.totalPoints ?? 0) + totalPoints,
      validUntil: Date.now() + 5 * 60 * 1_000,
    };

    this.updateState({
      availablePoints: this.state.availablePoints - totalPoints,
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
    const activeRedemption = this.state.activeRedemption;
    if (activeRedemption && activeRedemption.validUntil <= Date.now()) {
      this.updateState({ ...this.state, activeRedemption: null });
      return null;
    }

    return activeRedemption;
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
      const reward = this.rewards.find((item) => item.id === rewardId);
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

  private createOverview(): RewardsOverview {
    return {
      availablePoints: this.state.availablePoints,
      rewards: this.rewards,
      history: this.state.history,
      activeRedemption: this.state.activeRedemption,
    };
  }
}
