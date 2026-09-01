import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { RewardRepository, type ActiveRedemption } from '../../core/rewards/reward.repository';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { statusMessages } from '../../core/i18n/status-messages';

@Component({
  selector: 'app-active-redemption-page',
  imports: [NgIcon],
  templateUrl: './active-redemption.page.html',
})
export class ActiveRedemptionPage implements OnInit, OnDestroy {
  private readonly rewardRepository = inject(RewardRepository);
  private readonly router = inject(Router);
  private readonly statusMessages = inject(StatusMessageService);
  private timer: ReturnType<typeof setInterval> | undefined;
  private pollTimer: ReturnType<typeof setInterval> | undefined;

  protected readonly redemption = signal<ActiveRedemption | null>(null);
  protected readonly remainingSeconds = signal(0);
  protected readonly wasCancelled = signal(false);
  protected readonly remainingTime = computed(() => {
    const seconds = this.remainingSeconds();
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  });

  async ngOnInit(): Promise<void> {
    const redemption = await this.rewardRepository.getActiveRedemption();
    if (!redemption) {
      void this.router.navigate(['/punkte']);
      return;
    }

    this.redemption.set(redemption);
    this.updateRemainingTime();
    this.timer = setInterval(() => this.updateRemainingTime(), 1_000);
    this.pollTimer = setInterval(() => void this.checkActiveRedemption(), 5_000);
  }

  ngOnDestroy(): void {
    if (this.timer !== undefined) {
      clearInterval(this.timer);
    }
    if (this.pollTimer !== undefined) {
      clearInterval(this.pollTimer);
    }
  }

  protected backToRewards(): void {
    void this.router.navigate(['/punkte']);
  }

  private updateRemainingTime(): void {
    const redemption = this.redemption();
    if (!redemption) {
      return;
    }

    const remainingSeconds = Math.max(0, Math.ceil((redemption.validUntil - Date.now()) / 1_000));
    this.remainingSeconds.set(remainingSeconds);

    if (remainingSeconds === 0) {
      this.ngOnDestroy();
    }
  }

  private async checkActiveRedemption(): Promise<void> {
    if (!await this.rewardRepository.getActiveRedemption()) {
      this.redemption.set(null);
      this.wasCancelled.set(true);
      this.statusMessages.show(statusMessages.redemptionCancelled(), { kind: 'error', durationMs: 8_000 });
      this.ngOnDestroy();
    }
  }
}
