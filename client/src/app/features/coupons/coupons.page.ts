import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { CouponService, type Coupon, type CouponRedemption } from '../../core/coupons/coupon.service';
import { AnalyticsService } from '../../core/analytics/analytics.service';

@Component({
  templateUrl: './coupons.page.html',
  styles: [':host{display:block}.prose :is(img){max-width:100%;height:auto}button{cursor:pointer}button:disabled{cursor:not-allowed}'],
  imports: [RouterLink],
  host: {
    '(window:scroll)': 'loadMoreWhenNearBottom()',
  },
})
export class CouponsPage implements OnInit, OnDestroy {
  private static readonly pageSize = 8;
  private readonly service = inject(CouponService);
  private readonly analytics = inject(AnalyticsService);
  private readonly router = inject(Router);
  private readonly poller = setInterval(() => void this.refreshActive(), 5_000);

  protected readonly coupons = signal<readonly Coupon[]>([]);
  protected readonly cart = signal<readonly Coupon[]>([]);
  protected readonly activeRedemption = signal<CouponRedemption | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly isRedeeming = signal(false);
  protected readonly isConfirmationOpen = signal(false);
  protected readonly error = signal('');
  protected readonly filterText = signal('');
  protected readonly visibleCouponCount = signal(CouponsPage.pageSize);
  protected readonly cartCount = computed(() => this.cart().length);
  protected readonly filteredCoupons = computed(() => {
    const query = this.filterText().trim().toLocaleLowerCase('de');
    return query === ''
      ? this.coupons()
      : this.coupons().filter((coupon) => coupon.title.toLocaleLowerCase('de').includes(query));
  });
  protected readonly displayedCoupons = computed(() => this.filteredCoupons().slice(0, this.visibleCouponCount()));
  protected readonly hasMoreCoupons = computed(() => this.displayedCoupons().length < this.filteredCoupons().length);

  async ngOnInit(): Promise<void> {
    try { await Promise.all([this.refreshCoupons(), this.refreshActive()]); }
    finally { this.isLoading.set(false); }
  }

  ngOnDestroy(): void { clearInterval(this.poller); }

  protected addCoupon(coupon: Coupon): void {
    if (this.isLoading() || this.isRedeeming() || coupon.isRedeemed || this.cart().some(item => item.id === coupon.id)) return;
    this.cart.update(items => [...items, coupon]);
    this.analytics.trackItemSelection({ itemId: coupon.id, itemName: coupon.title, itemCategory: 'coupon' });
  }

  protected removeCoupon(id: number): void {
    if (this.isLoading() || this.isRedeeming()) return;
    this.cart.update(items => items.filter(item => item.id !== id));
  }

  protected selected(id: number): boolean { return this.cart().some(item => item.id === id); }

  protected filterByTitle(event: Event): void {
    this.filterText.set((event.target as HTMLInputElement).value);
    this.visibleCouponCount.set(CouponsPage.pageSize);
    queueMicrotask(() => this.loadMoreWhenNearBottom());
  }

  protected loadMoreCoupons(): void {
    if (this.hasMoreCoupons()) {
      this.visibleCouponCount.update((count) => count + CouponsPage.pageSize);
    }
  }

  protected loadMoreWhenNearBottom(): void {
    if (!this.hasMoreCoupons() || window.innerHeight + window.scrollY < document.documentElement.scrollHeight - 320) {
      return;
    }

    this.loadMoreCoupons();
  }

  protected openConfirmation(): void {
    if (this.cartCount() > 0 && !this.activeRedemption() && !this.isRedeeming()) this.isConfirmationOpen.set(true);
  }

  protected closeConfirmation(): void {
    if (!this.isRedeeming()) this.isConfirmationOpen.set(false);
  }

  protected async redeem(): Promise<void> {
    if (this.cartCount() === 0 || this.isRedeeming() || this.activeRedemption()) return;
    this.isRedeeming.set(true);
    this.isLoading.set(true);
    this.isConfirmationOpen.set(false);
    this.error.set('');
    try {
      const items = this.cart().map((item) => ({ itemId: item.id, itemName: item.title, itemCategory: 'coupon' as const }));
      this.analytics.trackCheckoutStart(items);
      await this.service.redeem(items.map(item => item.itemId));
      this.analytics.trackRedemption(items);
      this.cart.set([]);
      await this.refreshCoupons();
      void this.router.navigate(['/gutscheine/einloesung']);
    } catch {
      this.error.set('Die Gutschein-Auswahl konnte nicht eingelöst werden. Bitte prüfen Sie die verfügbaren Gutscheine und versuchen Sie es erneut.');
      await this.refreshCoupons();
      await this.refreshActive();
    } finally {
      this.isRedeeming.set(false);
      this.isLoading.set(false);
    }
  }

  private async refreshCoupons(): Promise<void> {
    try {
      const coupons = await this.service.list();
      this.coupons.set(coupons);
      this.visibleCouponCount.set(CouponsPage.pageSize);
      this.cart.update(items => items.filter(item => coupons.some(coupon => coupon.id === item.id && !coupon.isRedeemed)));
    } catch { this.error.set('Gutscheine konnten nicht geladen werden.'); }
  }

  private async refreshActive(): Promise<void> {
    try {
      const previous = this.activeRedemption();
      this.activeRedemption.set(await this.service.active());
      if (previous && !this.activeRedemption()) await this.refreshCoupons();
    } catch { /* Retry on the next poll. */ }
  }
}
