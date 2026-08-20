import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { RewardRepository, type ActiveRedemption } from '../../core/rewards/reward.repository';

interface PharmacyNews {
  readonly title: string;
  readonly excerpt: string;
  readonly category: string;
  readonly date: string;
}

@Component({
  selector: 'app-dashboard-page',
  imports: [NgIcon, RouterLink],
  templateUrl: './dashboard.page.html',
})
export class DashboardPage implements OnInit {
  private readonly rewardRepository = inject(RewardRepository);

  protected readonly activeRedemption = signal<ActiveRedemption | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly news: readonly PharmacyNews[] = [
    {
      category: 'Gesund durch den Sommer',
      title: 'Sonnenschutz: gut vorbereitet in den Urlaub',
      excerpt: 'Wir zeigen Ihnen, worauf es bei Hautschutz und Reiseapotheke ankommt.',
      date: 'Heute',
    },
    {
      category: 'Aktion',
      title: '20 % auf ausgewählte Pflegeprodukte',
      excerpt: 'Entdecken Sie unsere Angebote für eine sanfte tägliche Pflegeroutine.',
      date: '2. August',
    },
  ];

  async ngOnInit(): Promise<void> {
    try {
      this.activeRedemption.set(await this.rewardRepository.getActiveRedemption());
    } finally {
      this.isLoading.set(false);
    }
  }

  protected activeRewardCount(redemption: ActiveRedemption): number {
    return redemption.items.reduce((total, item) => total + item.quantity, 0);
  }
}
