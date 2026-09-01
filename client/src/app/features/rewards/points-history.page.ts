import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { RewardRepository, type PointsHistoryPage as PointsHistoryResult } from '../../core/rewards/reward.repository';

@Component({
  selector: 'app-points-history-page',
  imports: [RouterLink],
  templateUrl: './points-history.page.html',
})
export class PointsHistoryPage implements OnInit {
  private readonly rewards = inject(RewardRepository);
  protected readonly history = signal<PointsHistoryResult | null>(null);
  protected readonly isLoading = signal(true);

  async ngOnInit(): Promise<void> {
    await this.load(1);
  }

  protected async previousPage(): Promise<void> {
    const page = this.history();
    if (page !== null && page.page > 1) {
      await this.load(page.page - 1);
    }
  }

  protected async nextPage(): Promise<void> {
    const page = this.history();
    if (page !== null && page.page < page.totalPages) {
      await this.load(page.page + 1);
    }
  }

  private async load(page: number): Promise<void> {
    this.isLoading.set(true);
    try {
      this.history.set(await this.rewards.getHistory(page));
    } finally {
      this.isLoading.set(false);
    }
  }
}
