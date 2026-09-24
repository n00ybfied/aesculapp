import { HttpClient, HttpHeaders } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { firstValueFrom, forkJoin } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface RedemptionCustomerSummary {
  readonly id: number;
  readonly displayName: string;
  readonly username: string;
  readonly profileImageUrl: string | null;
}

export interface ActiveRewardRedemption {
  readonly id: number;
  readonly customer: string;
  readonly customerDetails: RedemptionCustomerSummary;
  readonly summary: string;
  readonly points: number;
  readonly validUntil: string;
}

export interface ActiveCouponRedemption {
  readonly id: string;
  readonly customer: string;
  readonly customerDetails: RedemptionCustomerSummary;
  readonly summary: string;
  readonly items: readonly { couponId: number; title: string }[];
  readonly validUntil: string;
}

@Injectable({ providedIn: 'root' })
export class AdminRedemptionService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1/admin'
    : 'https://api.aesculapp.floatbox.at/api/v1/admin';
  private loading = false;
  private revision = 0;
  private readonly countAvailable = signal(false);

  readonly rewards = signal<readonly ActiveRewardRedemption[]>([]);
  readonly coupons = signal<readonly ActiveCouponRedemption[]>([]);
  readonly activeCount = computed(() => this.countAvailable() ? this.rewards().length + this.coupons().length : null);
  readonly error = signal('');

  async refresh(): Promise<void> {
    if (this.loading) return;
    this.loading = true;
    const revision = this.revision;
    try {
      const result = await firstValueFrom(forkJoin({
        rewards: this.http.get<{ redemptions: ActiveRewardRedemption[] }>(`${this.api}/redemptions/active`, this.options()),
        coupons: this.http.get<{ redemptions: ActiveCouponRedemption[] }>(`${this.api}/coupons/redemptions/active`, this.options()),
      }));
      if (revision !== this.revision) return;
      this.rewards.set(result.rewards.redemptions);
      this.coupons.set(result.coupons.redemptions);
      this.countAvailable.set(true);
      this.error.set('');
    } catch {
      if (revision !== this.revision) return;
      this.countAvailable.set(false);
      this.error.set('Aktive Einlösungen konnten nicht geladen werden.');
    } finally {
      this.loading = false;
    }
  }

  async cancelReward(id: number): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api}/redemptions/${id}/cancel`, {}, this.options()));
    this.revision += 1;
    this.rewards.update((items) => items.filter((item) => item.id !== id));
  }

  async cancelCoupon(id: string): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api}/coupons/redemptions/${id}/cancel`, {}, this.options()));
    this.revision += 1;
    this.coupons.update((items) => items.filter((item) => item.id !== id));
  }

  clear(): void {
    this.revision += 1;
    this.rewards.set([]);
    this.coupons.set([]);
    this.countAvailable.set(false);
    this.error.set('');
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) };
  }
}
