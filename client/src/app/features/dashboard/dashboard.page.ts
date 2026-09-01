import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { RewardRepository, type ActiveRedemption, type RewardsOverview } from '../../core/rewards/reward.repository';
import { PharmacyNewsService, type PharmacyNewsPost } from '../../core/news/pharmacy-news.service';

@Component({
  selector: 'app-dashboard-page',
  imports: [NgIcon, RouterLink],
  templateUrl: './dashboard.page.html',
})
export class DashboardPage implements OnInit {
  private readonly rewardRepository = inject(RewardRepository);
  private readonly newsService = inject(PharmacyNewsService);

  protected readonly activeRedemption = signal<ActiveRedemption | null>(null);
  protected readonly pointsOverview = signal<RewardsOverview | null>(null);
  protected readonly nextReward = computed(() => {
    const overview = this.pointsOverview();
    return overview?.rewards.find((reward) => reward.requiredPoints > overview.availablePoints) ?? null;
  });
  protected readonly isLoading = signal(true);
  protected readonly news = signal<readonly PharmacyNewsPost[]>([]);

  async ngOnInit(): Promise<void> {
    try {
      const [overview, news] = await Promise.all([this.rewardRepository.getOverview(), this.newsService.getLatest().catch(() => [])]);
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
}
