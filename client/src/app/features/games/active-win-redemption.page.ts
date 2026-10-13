import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { GamesService, WinRedemption } from '../../core/games/games.service';

@Component({
  template: `
    <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center px-5 py-8">
      @if (loading()) { <span class="size-10 animate-spin rounded-full border-4 border-accent border-t-primary" role="status" aria-label="Einlösung wird geladen"></span> }
      @else if (cancelled()) {
        <section class="w-full rounded-3xl bg-danger-soft p-7 text-center shadow-soft" role="alert">
          <h1 class="text-3xl font-bold">Einlösung abgebrochen</h1>
          <p class="mt-4 text-muted">Das Apotheken-Team hat Ihre Gewinne wieder freigegeben.</p>
          <button type="button" class="mt-8 min-h-12 w-full rounded-xl bg-primary px-5 font-bold text-on-primary" (click)="back()">Zurück zu meinen Gewinnen</button>
        </section>
      } @else if (redemption(); as active) {
        <section class="w-full rounded-3xl bg-primary p-7 text-center text-on-primary shadow-soft" aria-labelledby="win-redemption-title">
          @if (remainingSeconds() > 0) {
            <p class="text-sm font-bold tracking-wide text-on-primary/80">DEM APOTHEKEN-TEAM ZEIGEN</p>
            <h1 id="win-redemption-title" class="redemption-heading mt-2 text-3xl font-bold text-on-primary">Ihre Gewinne</h1>
            <ul class="mt-5 space-y-3 text-left">
              @for (item of active.items; track item.winId) { <li class="rounded-2xl bg-white/12 p-3"><strong class="block">{{ item.title }}</strong><span class="text-sm text-on-primary/80">{{ item.gameTitle }}</span></li> }
            </ul>
            <p class="mt-4 text-sm text-on-primary/85">Diese Einlösung ist noch gültig für</p>
            <p class="mt-2 text-6xl font-bold tabular-nums">{{ remainingTime() }}</p>
          } @else {
            <h1 id="win-redemption-title" class="redemption-heading text-3xl font-bold text-on-primary">Einlösung abgelaufen</h1>
            <p class="mt-4 text-on-primary/85">Diese Gewinne können nicht mehr vorgezeigt werden und bleiben eingelöst.</p>
          }
          <button type="button" class="mt-8 min-h-12 w-full rounded-xl bg-white px-5 font-bold text-primary" (click)="back()">Zurück zu meinen Gewinnen</button>
        </section>
      }
    </main>
  `,
})
export class ActiveWinRedemptionPage implements OnInit, OnDestroy {
  private readonly service = inject(GamesService);
  private readonly router = inject(Router);
  private timer: ReturnType<typeof setInterval> | undefined;
  private poller: ReturnType<typeof setInterval> | undefined;
  protected readonly redemption = signal<WinRedemption | null>(null);
  protected readonly remainingSeconds = signal(0);
  protected readonly loading = signal(true);
  protected readonly cancelled = signal(false);
  protected readonly remainingTime = computed(() => `${Math.floor(this.remainingSeconds() / 60)}:${String(this.remainingSeconds() % 60).padStart(2, '0')}`);

  async ngOnInit(): Promise<void> {
    try {
      const active = await this.service.activeWinRedemption();
      if (!active) { this.back(); return; }
      this.redemption.set(active);
      this.tick();
      this.timer = setInterval(() => this.tick(), 1_000);
      this.poller = setInterval(() => void this.checkActive(), 5_000);
    } catch { this.back(); }
    finally { this.loading.set(false); }
  }

  ngOnDestroy(): void {
    if (this.timer) clearInterval(this.timer);
    if (this.poller) clearInterval(this.poller);
  }

  protected back(): void { void this.router.navigate(['/gewinnspiele/gewinne']); }

  private tick(): void {
    const active = this.redemption();
    if (!active) return;
    this.remainingSeconds.set(Math.max(0, Math.ceil((new Date(active.validUntil).getTime() - Date.now()) / 1000)));
    if (this.remainingSeconds() === 0) this.ngOnDestroy();
  }

  private async checkActive(): Promise<void> {
    try {
      if (!await this.service.activeWinRedemption() && this.remainingSeconds() > 0) {
        this.cancelled.set(true);
        this.ngOnDestroy();
      }
    } catch { /* Beim nächsten Poll erneut versuchen. */ }
  }
}
