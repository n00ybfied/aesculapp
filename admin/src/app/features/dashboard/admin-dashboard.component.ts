import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AdminStatisticsService, type AdminStatistics } from '../../core/statistics/admin-statistics.service';
import { OpenChatBadgeComponent } from '../../shared/open-chat-badge.component';

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink, OpenChatBadgeComponent],
  templateUrl: './admin-dashboard.component.html',
  styleUrl: './admin-dashboard.component.css',
})
export class AdminDashboardComponent {
  private readonly statisticsService = inject(AdminStatisticsService);
  protected readonly statistics = signal<AdminStatistics | null>(null);
  protected readonly chartPoints = computed(() => {
    const timeline = this.statistics()?.users.timeline ?? [];
    if (timeline.length === 0) return '';
    const values = timeline.map((point) => point.totalUsers);
    const min = Math.min(...values);
    const range = Math.max(1, Math.max(...values) - min);
    return timeline.map((point, index) => {
      const x = 4 + index * 92 / Math.max(1, timeline.length - 1);
      const y = 91 - (point.totalUsers - min) * 72 / range;
      return `${x},${y}`;
    }).join(' ');
  });

  constructor() { void this.loadStatistics(); }

  protected shortDate(value: string): string {
    return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit' }).format(new Date(`${value}T12:00:00`));
  }

  private async loadStatistics(): Promise<void> {
    try { this.statistics.set(await this.statisticsService.get()); } catch { /* Dashboard remains useful when statistics are temporarily unavailable. */ }
  }
}
