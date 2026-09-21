import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminRewardService, type AdminReward, type AdminRewardPage } from '../../core/rewards/admin-reward.service';

@Component({selector:'app-admin-rewards',imports:[FormsModule, RouterLink],templateUrl:'./admin-rewards.component.html',styleUrl:'./admin-rewards.component.css'})
export class AdminRewardsComponent {
  private readonly service = inject(AdminRewardService);
  protected readonly rewardPage = signal<AdminRewardPage | null>(null);
  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal('');
  protected query = '';
  protected pageSize: 10 | 25 | 50 | 'all' = 25;

  constructor() { void this.load(); }

  protected async load(page = 1): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try { this.rewardPage.set(await this.service.list(page, this.pageSize, this.query.trim())); } catch { this.error.set('Prämien konnten nicht geladen werden.'); } finally { this.loading.set(false); }
  }

  protected applyFilters(): void { void this.load(); }

  protected updatePageSize(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.pageSize = value === 'all' ? 'all' : Number(value) as 10 | 25 | 50;
    void this.load();
  }

  protected async toggle(reward: AdminReward): Promise<void> {
    if (this.busy()) return;
    this.busy.set(true);
    try { await this.service.toggle(reward); await this.load(this.rewardPage()?.page ?? 1); } catch { this.error.set('Sichtbarkeit konnte nicht geändert werden.'); } finally { this.busy.set(false); }
  }

  protected async remove(reward: AdminReward): Promise<void> {
    if (this.busy() || !confirm('„' + reward.title + '“ wirklich löschen?')) return;
    this.busy.set(true);
    try { await this.service.remove(reward.id); await this.load(this.rewardPage()?.page ?? 1); } catch { this.error.set('Prämie konnte nicht gelöscht werden.'); } finally { this.busy.set(false); }
  }
}
