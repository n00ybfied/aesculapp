import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CouponService, type CouponRedemption } from '../../core/coupons/coupon.service';

@Component({
  template: `
    <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center px-5 py-8">
      @if (loading()) {
        <span class="size-10 animate-spin rounded-full border-4 border-accent border-t-primary" role="status" aria-label="Einlösung wird geladen"></span>
      } @else if (wasCancelled()) {
        <section class="w-full rounded-3xl bg-danger-soft p-7 text-center shadow-soft" role="alert">
          <p class="text-sm font-bold tracking-wide text-danger">EINLÖSUNG ABGEBROCHEN</p>
          <h1 class="mt-2 text-3xl font-bold">Ihre Gutscheine wurden wieder freigegeben</h1>
          <p class="mt-4 text-sm leading-6 text-muted">Das Apotheken-Team hat diese Einlösung abgebrochen.</p>
          <button type="button" class="mt-8 min-h-12 w-full rounded-xl bg-primary px-5 font-bold text-on-primary" (click)="back()">Zurück zu Gutscheinen</button>
        </section>
      } @else if (redemption(); as active) {
        <section class="w-full rounded-3xl bg-primary p-7 text-center text-on-primary shadow-soft" aria-labelledby="coupon-redemption-title">
          @if (remainingSeconds() > 0) {
            <p class="text-sm font-bold tracking-wide text-on-primary/80">DEM APOTHEKEN-TEAM ZEIGEN</p>
            <h1 id="coupon-redemption-title" class="mt-2 text-3xl font-bold">Ihre Gutscheine</h1>
            <ul class="mt-5 space-y-3 text-left">
              @for (item of active.items; track item.couponId) {
                <li class="flex items-center gap-3 rounded-2xl bg-white/12 p-3">
                  @if (item.imageUrl) { <img class="size-16 shrink-0 rounded-xl object-cover" [src]="item.imageUrl" alt="" /> }
                  <span class="min-w-0"><strong class="block text-base leading-5">{{ item.title }}</strong><span class="mt-1 block text-sm leading-5 text-on-primary/80">{{ item.subtitle }}</span></span>
                </li>
              }
            </ul>
            <p class="mt-4 text-sm leading-6 text-on-primary/85">Diese Einlösung ist noch gültig für</p>
            <p class="mt-2 text-6xl font-bold tracking-tight tabular-nums">{{ remainingTime() }}</p>
          } @else {
            <p class="text-sm font-bold tracking-wide text-on-primary/80">EINLÖSUNG ABGELAUFEN</p>
            <h1 id="coupon-redemption-title" class="mt-2 text-3xl font-bold">Leider zu spät</h1>
            <p class="mt-4 text-sm leading-6 text-on-primary/85">Diese Gutscheine können nicht mehr vorgezeigt werden und bleiben eingelöst.</p>
          }
          <button type="button" class="mt-8 min-h-12 w-full rounded-xl bg-white px-5 font-bold text-primary" (click)="back()">Zurück zu Gutscheinen</button>
        </section>
      }
    </main>
  `,
  styles: ['button{cursor:pointer}'],
})
export class ActiveCouponRedemptionPage implements OnInit, OnDestroy {
  private readonly service = inject(CouponService);
  private readonly router = inject(Router);
  private timer: ReturnType<typeof setInterval> | undefined;
  private poller: ReturnType<typeof setInterval> | undefined;

  protected readonly redemption = signal<CouponRedemption | null>(null);
  protected readonly remainingSeconds = signal(0);
  protected readonly loading = signal(true);
  protected readonly wasCancelled = signal(false);
  protected readonly remainingTime = computed(() => {
    const seconds = this.remainingSeconds();
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
  });

  async ngOnInit(): Promise<void> {
    try {
      const active = await this.service.active();
      if (!active) { void this.router.navigate(['/gutscheine']); return; }
      this.redemption.set(active);
      this.tick();
      this.timer = setInterval(() => this.tick(), 1_000);
      this.poller = setInterval(() => void this.checkActive(), 5_000);
    } catch { void this.router.navigate(['/gutscheine']); }
    finally { this.loading.set(false); }
  }

  ngOnDestroy(): void {
    if (this.timer !== undefined) clearInterval(this.timer);
    if (this.poller !== undefined) clearInterval(this.poller);
  }

  protected back(): void { void this.router.navigate(['/gutscheine']); }

  private tick(): void {
    const active = this.redemption();
    if (!active) return;
    const seconds = Math.max(0, Math.ceil((new Date(active.validUntil).getTime() - Date.now()) / 1_000));
    this.remainingSeconds.set(seconds);
    if (seconds === 0) this.ngOnDestroy();
  }

  private async checkActive(): Promise<void> {
    try {
      if (!await this.service.active() && this.remainingSeconds() > 0) {
        this.wasCancelled.set(true);
        this.ngOnDestroy();
      }
    } catch { /* Try again on the next poll. */ }
  }
}
