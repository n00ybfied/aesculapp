import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { RewardRepository, type ActiveRedemption, type RewardsOverview } from '../../core/rewards/reward.repository';
import { PharmacyNewsService, type PharmacyNewsPost } from '../../core/news/pharmacy-news.service';
import { AuthService } from '../../core/auth/auth.service';
import { ProfileService } from '../../core/profile/profile.service';

@Component({
  selector: 'app-dashboard-page',
  imports: [NgIcon, RouterLink],
  templateUrl: './dashboard.page.html',
})
export class DashboardPage implements OnInit {
  private readonly rewardRepository = inject(RewardRepository);
  private readonly newsService = inject(PharmacyNewsService);
  private readonly auth = inject(AuthService);
  private readonly profiles = inject(ProfileService);

  protected readonly activeRedemption = signal<ActiveRedemption | null>(null);
  protected readonly pointsOverview = signal<RewardsOverview | null>(null);
  protected readonly nextReward = computed(() => {
    const overview = this.pointsOverview();
    return overview?.rewards.find((reward) => reward.requiredPoints > overview.availablePoints) ?? null;
  });
  protected readonly isLoading = signal(true);
  protected readonly news = signal<readonly PharmacyNewsPost[]>([]);
  protected readonly pushHintDismissed = signal(true);
  protected readonly pushHintVisible = computed(() => {
    const profile = this.profiles.profile();
    return profile !== null && !this.pushHintDismissed() && !profile.chatPushEnabled && !profile.rewardPushEnabled && !profile.newsPushEnabled;
  });

  async ngOnInit(): Promise<void> {
    this.restorePushHint();
    try {
      const [overview, news] = await Promise.all([this.rewardRepository.getOverview(), this.newsService.getLatest().catch(() => []), this.profiles.load().catch(() => null)]);
      this.pointsOverview.set(overview);
      this.activeRedemption.set(overview.activeRedemption);
      this.news.set(news);
    } finally {
      this.isLoading.set(false);
    }
  }

  protected activeRewardCount(redemption: ActiveRedemption): number {
    return redemption.items.reduce((total, item) => total + item.quantity, 0);
  }
  protected formatNewsDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium' }).format(new Date(value)); }

  protected dismissPushHint(): void {
    this.pushHintDismissed.set(true);
    const user = this.auth.currentUser();
    if (user === null) return;
    try { localStorage.setItem(this.pushHintKey(user.id), String(Date.now() + 30 * 24 * 60 * 60 * 1_000)); } catch { /* Private mode may block storage. */ }
  }

  private restorePushHint(): void {
    const user = this.auth.currentUser();
    if (user === null) return;
    try {
      const dismissedUntil = Number(localStorage.getItem(this.pushHintKey(user.id)) ?? '0');
      this.pushHintDismissed.set(Number.isFinite(dismissedUntil) && dismissedUntil > Date.now());
    } catch { this.pushHintDismissed.set(false); }
  }

  private pushHintKey(userId: number): string { return `aesculapp.push-categories-hint.v1.${userId}`; }
}
