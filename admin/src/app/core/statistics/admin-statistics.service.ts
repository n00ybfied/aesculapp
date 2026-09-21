import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface StatisticsItem {
  readonly title: string;
  readonly views?: number;
  readonly redemptions?: number;
  readonly type?: string;
}

export interface AdminStatistics {
  readonly periodDays: number;
  readonly users: { readonly total: number; readonly timeline: readonly { readonly date: string; readonly totalUsers: number; readonly newUsers: number; }[]; };
  readonly catalog: { readonly news: number; readonly rewards: number; readonly coupons: number; };
  readonly engagement: { readonly activeUsers: number; readonly totalMinutes: number; readonly averageMinutesPerActiveUser: number; readonly mostViewed: readonly StatisticsItem[]; readonly longestSessions: readonly (StatisticsItem & { readonly minutes: number })[]; };
  readonly redemptions: { readonly totalRewards: number; readonly totalCoupons: number; readonly rewards: readonly StatisticsItem[]; readonly coupons: readonly StatisticsItem[]; readonly topCustomers: readonly StatisticsItem[]; };
}

@Injectable({ providedIn: 'root' })
export class AdminStatisticsService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);

  get(): Promise<AdminStatistics> {
    return firstValueFrom(this.http.get<AdminStatistics>(`${this.api()}/admin/statistics`, { headers: this.headers() }));
  }

  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
