import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export type AnalyticsItemType = 'news' | 'reward' | 'coupon';
export type AnalyticsEventName = 'view_item' | 'select_item' | 'begin_checkout' | 'redeem_item' | 'user_engagement';

export interface AnalyticsItem {
  readonly itemId: number;
  readonly itemName: string;
  readonly itemCategory: AnalyticsItemType;
  readonly quantity?: number;
}

export interface AnalyticsEvent {
  readonly eventName: AnalyticsEventName;
  readonly items?: readonly AnalyticsItem[];
  readonly engagementTimeMsec?: number;
}

@Injectable({ providedIn: 'root' })
export class AnalyticsService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);
  private lastSentAt: number | null = null;
  private timer: ReturnType<typeof setInterval> | undefined;

  /** GA4-compatible contract; a future GA adapter belongs centrally in dispatch(). */
  trackItemView(item: AnalyticsItem): void { this.dispatch({ eventName: 'view_item', items: [item] }); }
  trackItemSelection(item: AnalyticsItem): void { this.dispatch({ eventName: 'select_item', items: [item] }); }
  trackCheckoutStart(items: readonly AnalyticsItem[]): void { this.dispatch({ eventName: 'begin_checkout', items }); }
  trackRedemption(items: readonly AnalyticsItem[]): void { items.forEach((item) => this.dispatch({ eventName: 'redeem_item', items: [item] })); }

  startSession(): void {
    if (this.lastSentAt !== null) return;
    this.lastSentAt = Date.now();
    this.timer = setInterval(() => this.flushSessionDuration(), 60_000);
  }

  stopSession(): void {
    this.flushSessionDuration();
    if (this.timer !== undefined) clearInterval(this.timer);
    this.timer = undefined;
    this.lastSentAt = null;
  }

  flushSessionDuration(): void {
    if (document.hidden || this.lastSentAt === null) return;
    const now = Date.now();
    const seconds = Math.min(300, Math.floor((now - this.lastSentAt) / 1_000));
    if (seconds < 1) return;
    this.lastSentAt = now;
    this.dispatch({ eventName: 'user_engagement', engagementTimeMsec: seconds * 1_000 });
  }

  private dispatch(event: AnalyticsEvent): void {
    const item = event.items?.[0];
    const payload = {
      eventName: event.eventName,
      itemType: item?.itemCategory,
      itemId: item?.itemId,
      engagementTimeMsec: event.engagementTimeMsec,
    };
    // When GA4 is added, this is the sole integration point. The event and item
    // fields already use GA4's recommended event/item naming convention.
    void firstValueFrom(this.http.post<void>(`${this.apiBaseUrl}/analytics/events`, payload, {
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }),
    })).catch(() => undefined);
  }
}
