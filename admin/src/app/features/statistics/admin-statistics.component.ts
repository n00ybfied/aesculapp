import { Component, computed, inject, signal } from '@angular/core';
import { AdminStatisticsService, type AdminStatistics, type StatisticsItem } from '../../core/statistics/admin-statistics.service';

@Component({
  selector: 'app-admin-statistics',
  templateUrl: './admin-statistics.component.html',
  styleUrl: './admin-statistics.component.css',
})
export class AdminStatisticsComponent {
  private readonly statisticsService = inject(AdminStatisticsService);
  protected readonly statistics = signal<AdminStatistics | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly error = signal('');
  protected readonly chartPoints = computed(() => {
    const timeline = this.statistics()?.users.timeline ?? [];
    if (timeline.length === 0) return '';
    const values = timeline.map(point => point.totalUsers);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = Math.max(1, max - min);
    return timeline.map((point, index) => {
      const x = 4 + index * 92 / Math.max(1, timeline.length - 1);
      const y = 92 - (point.totalUsers - min) * 76 / range;
      return `${x},${y}`;
    }).join(' ');
  });
  protected readonly chartLabels = computed(() => {
    const timeline = this.statistics()?.users.timeline ?? [];
    return [timeline.at(0), timeline.at(Math.floor(timeline.length / 2)), timeline.at(-1)].filter((item): item is NonNullable<typeof item> => item !== undefined);
  });

  constructor() { void this.load(); }

  protected retry(): void { void this.load(); }
  protected itemValue(item: StatisticsItem): number { return item.views ?? item.redemptions ?? 0; }
  protected itemLabel(item: StatisticsItem): string { return item.views === undefined ? 'Einlösungen' : 'Aufrufe'; }
  protected shortDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit' }).format(new Date(`${value}T12:00:00`)); }

  private async load(): Promise<void> {
    this.isLoading.set(true);
    this.error.set('');
    try { this.statistics.set(await this.statisticsService.get()); }
    catch { this.error.set('Die Statistik konnte nicht geladen werden. Bitte versuchen Sie es erneut.'); }
    finally { this.isLoading.set(false); }
  }
}
