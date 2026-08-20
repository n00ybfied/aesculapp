import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { statusMessages } from '../../core/i18n/status-messages';
import { RewardRepository, type ActiveRedemption, type Reward, type RewardsOverview } from '../../core/rewards/reward.repository';

interface RewardCartItem {
  readonly reward: Reward;
  readonly quantity: number;
}

@Component({
  selector: 'app-rewards-page',
  imports: [NgIcon],
  templateUrl: './rewards.page.html',
})
export class RewardsPage implements OnInit {
  private readonly rewardRepository = inject(RewardRepository);
  private readonly statusMessages = inject(StatusMessageService);
  private readonly router = inject(Router);

  protected readonly overview = signal<RewardsOverview | null>(null);
  protected readonly cart = signal<readonly RewardCartItem[]>([]);
  protected readonly isConfirmationOpen = signal(false);
  protected readonly isRedeeming = signal(false);
  protected readonly isLoading = signal(true);
  protected readonly cartTotal = computed(() => this.cart().reduce(
    (total, item) => total + item.reward.requiredPoints * item.quantity,
    0,
  ));
  protected readonly cartItemCount = computed(() => this.cart().reduce((total, item) => total + item.quantity, 0));
  protected readonly availablePointsForRewards = computed(() => Math.max(
    0,
    (this.overview()?.availablePoints ?? 0) - this.cartTotal(),
  ));
  protected readonly hasEnoughPoints = computed(() => {
    const overview = this.overview();
    return overview !== null && this.cartTotal() <= overview.availablePoints;
  });
  protected readonly nextReward = computed(() => {
    const overview = this.overview();
    return overview?.rewards.find((reward) => reward.requiredPoints > overview.availablePoints) ?? null;
  });

  async ngOnInit(): Promise<void> {
    await this.loadOverview();
  }

  protected addReward(reward: Reward): void {
    if (this.isLoading() || this.isRedeeming()) {
      return;
    }

    const overview = this.overview();
    if (!overview || this.cartTotal() + reward.requiredPoints > overview.availablePoints) {
      this.statusMessages.show(statusMessages.notEnoughPoints(), { kind: 'error' });
      return;
    }

    this.cart.update((items) => {
      const existing = items.find((item) => item.reward.id === reward.id);
      return existing
        ? items.map((item) => item.reward.id === reward.id ? { ...item, quantity: item.quantity + 1 } : item)
        : [...items, { reward, quantity: 1 }];
    });
  }

  protected removeReward(rewardId: string): void {
    if (this.isLoading() || this.isRedeeming()) {
      return;
    }

    this.cart.update((items) => items.flatMap((item) => {
      if (item.reward.id !== rewardId) {
        return [item];
      }

      return item.quantity > 1 ? [{ ...item, quantity: item.quantity - 1 }] : [];
    }));
  }

  protected openConfirmation(): void {
    if (!this.isLoading() && !this.isRedeeming() && this.cartItemCount() > 0 && this.hasEnoughPoints()) {
      this.isConfirmationOpen.set(true);
    }
  }

  protected closeConfirmation(): void {
    if (!this.isRedeeming()) {
      this.isConfirmationOpen.set(false);
    }
  }

  protected async redeem(): Promise<void> {
    if (this.cartItemCount() === 0 || !this.hasEnoughPoints() || this.isRedeeming() || this.isLoading()) {
      return;
    }

    this.isRedeeming.set(true);
    this.isLoading.set(true);
    this.isConfirmationOpen.set(false);

    try {
      await this.rewardRepository.redeem(this.cart().map((item) => ({
        rewardId: item.reward.id,
        quantity: item.quantity,
      })));
      await this.refreshOverview();
      this.cart.set([]);
      void this.router.navigate(['/punkte/einloesung']);
    } catch {
      this.statusMessages.show(statusMessages.notEnoughPoints(), { kind: 'error' });
      await this.refreshOverview();
    } finally {
      this.isRedeeming.set(false);
      this.isLoading.set(false);
    }
  }

  protected async resetPoints(): Promise<void> {
    if (this.isLoading() || this.isRedeeming()) {
      return;
    }

    this.isLoading.set(true);
    try {
      await this.rewardRepository.reset();
      this.cart.set([]);
      await this.refreshOverview();
      this.statusMessages.show(statusMessages.pointsReset(), { kind: 'info' });
    } finally {
      this.isLoading.set(false);
    }
  }

  protected showActiveRedemption(): void {
    void this.router.navigate(['/punkte/einloesung']);
  }

  protected activeRewardCount(redemption: ActiveRedemption): number {
    return redemption.items.reduce((total, item) => total + item.quantity, 0);
  }

  protected rewardProgress(reward: Reward): number {
    return Math.min(100, (this.availablePointsForRewards() / reward.requiredPoints) * 100);
  }

  private async loadOverview(): Promise<void> {
    this.isLoading.set(true);

    try {
      await this.refreshOverview();
    } finally {
      this.isLoading.set(false);
    }
  }

  private async refreshOverview(): Promise<void> {
    this.overview.set(await this.rewardRepository.getOverview());
  }
}
