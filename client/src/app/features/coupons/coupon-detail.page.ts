import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AnalyticsService } from '../../core/analytics/analytics.service';
import { CouponService, type Coupon } from '../../core/coupons/coupon.service';

@Component({
  selector: 'app-coupon-detail-page',
  imports: [RouterLink],
  templateUrl: './coupon-detail.page.html',
})
export class CouponDetailPage {
  private readonly route = inject(ActivatedRoute);
  private readonly coupons = inject(CouponService);
  private readonly analytics = inject(AnalyticsService);
  protected readonly coupon = signal<Coupon | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly error = signal('');

  constructor() { void this.load(); }

  private async load(): Promise<void> {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!Number.isInteger(id) || id < 1) {
      this.error.set('Dieser Gutschein ist nicht verfügbar.');
      this.isLoading.set(false);
      return;
    }
    try {
      const coupon = await this.coupons.getOne(id);
      this.coupon.set(coupon);
      if (coupon === null) this.error.set('Dieser Gutschein ist nicht verfügbar.');
      else this.analytics.trackItemView({ itemId: coupon.id, itemName: coupon.title, itemCategory: 'coupon' });
    } catch {
      this.error.set('Der Gutschein konnte nicht geladen werden. Bitte versuchen Sie es später erneut.');
    } finally {
      this.isLoading.set(false);
    }
  }
}
