import { DatePipe } from '@angular/common';
import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { GamesService, GameSpin, WinRedemption } from '../../core/games/games.service';
import { ThemeService } from '../../core/theme/theme.service';

@Component({
  selector: 'app-game-wins',
  imports: [DatePipe, RouterLink],
  template: `
    <main class="mx-auto max-w-[500px] space-y-5 bg-background px-5 py-8">
      <a routerLink="/gewinnspiele" class="text-sm font-semibold text-primary">← Gewinnspiele</a>
      <h1 class="text-3xl font-bold">Meine Gewinne</h1>
      <p class="text-muted">Wählen Sie Ihre Gewinngutscheine aus und zeigen Sie sie gemeinsam dem Apotheken-Team.</p>
      @if (loading()) { <div class="grid min-h-64 place-items-center" role="status">Lädt …</div> }
      @else {
        @if (error()) { <p class="rounded-xl bg-danger-soft p-4 text-danger" role="alert">{{ error() }}</p> }
        @if (active()) {
          <section class="rounded-2xl border border-primary bg-accent p-4">
            <p class="font-bold text-primary">Aktive Gewinn-Einlösung</p>
            <a routerLink="/gewinnspiele/gewinne/einloesung" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-primary px-4 py-2 font-bold text-on-primary">Dem Team zeigen</a>
          </section>
        }
        @for (win of wins(); track win.id) {
          <article class="rounded-3xl border border-border bg-surface p-5 shadow-soft" [class.opacity-55]="win.redeemedAt" [class.grayscale]="win.redeemedAt">
            <p class="text-sm font-bold text-primary">{{ win.gameTitle }}</p><h2 class="mt-1 text-xl font-bold">{{ win.title }}</h2>
            <p class="mt-2 text-sm text-muted">Gewonnen am {{ win.createdAt | date:'dd.MM.yyyy HH:mm' }}</p>
            @if (win.redeemedAt) { <p class="mt-3 font-semibold text-success">Eingelöst am {{ win.redeemedAt | date:'dd.MM.yyyy' }}</p> }
            @else if (selected(win.id)) { <button type="button" class="mt-4 min-h-11 rounded-xl border border-danger px-4 font-bold text-danger" (click)="toggle(win.id)">Auswahl entfernen</button> }
            @else { <button type="button" class="mt-4 min-h-11 rounded-xl bg-primary px-4 font-bold text-on-primary" (click)="toggle(win.id)" [disabled]="active() !== null">Hinzufügen</button> }
          </article>
        } @empty { <p class="rounded-2xl border border-border bg-surface p-5 text-muted">Noch keine Gutscheine gewonnen.</p> }
        @if (selectedCount() > 0) {
          <section class="sticky bottom-[calc(6.5rem+env(safe-area-inset-bottom))] rounded-2xl border border-primary bg-surface p-4 shadow-soft">
            <div class="flex items-center justify-between gap-3"><p class="font-bold">{{ selectedCount() }} {{ selectedCount() === 1 ? 'Gewinn' : 'Gewinne' }} ausgewählt</p><button type="button" class="min-h-11 rounded-xl bg-primary px-4 font-bold text-on-primary" (click)="confirming.set(true)" [disabled]="active() !== null || saving()">Einlösen</button></div>
          </section>
        }
      }
    </main>
    @if (confirming()) {
      <div class="fixed inset-0 z-50 grid place-items-center bg-foreground/45 p-5" role="presentation">
        <section class="w-full max-w-sm rounded-3xl bg-surface p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="win-redeem-title">
          <h2 id="win-redeem-title" class="text-2xl font-bold">Gewinne jetzt einlösen?</h2>
          <p class="mt-3 text-sm leading-6 text-muted">Die ausgewählten Gewinngutscheine können {{ theme.redemptionDurationMinutes }} {{ theme.redemptionDurationMinutes === 1 ? 'Minute' : 'Minuten' }} lang dem Apotheken-Team gezeigt werden. Danach bleiben sie verbraucht, sofern das Team die Einlösung nicht abbricht.</p>
          <button type="button" class="mt-6 min-h-12 w-full rounded-xl bg-primary px-5 font-bold text-on-primary" (click)="redeem()" [disabled]="saving()">Ja, jetzt einlösen</button>
          <button type="button" class="mt-3 min-h-11 w-full rounded-xl px-5 font-bold text-muted" (click)="confirming.set(false)" [disabled]="saving()">Nein</button>
        </section>
      </div>
    }
  `,
})
export class GameWinsPage implements OnInit, OnDestroy {
  private readonly service = inject(GamesService);
  protected readonly theme = inject(ThemeService);
  private readonly router = inject(Router);
  private poller: ReturnType<typeof setInterval> | undefined;
  protected readonly wins = signal<readonly GameSpin[]>([]);
  protected readonly active = signal<WinRedemption | null>(null);
  protected readonly selectedIds = signal<readonly number[]>([]);
  protected readonly selectedCount = computed(() => this.selectedIds().length);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly confirming = signal(false);
  protected readonly error = signal('');

  async ngOnInit(): Promise<void> {
    try { await this.refresh(); }
    catch { this.error.set('Gewinne konnten nicht geladen werden.'); }
    finally { this.loading.set(false); }
    this.poller = setInterval(() => void this.refresh().catch(() => undefined), 5_000);
  }

  ngOnDestroy(): void { if (this.poller) clearInterval(this.poller); }

  protected selected(id: number): boolean { return this.selectedIds().includes(id); }

  protected toggle(id: number): void {
    this.selectedIds.update((ids) => ids.includes(id) ? ids.filter((item) => item !== id) : [...ids, id]);
  }

  protected async redeem(): Promise<void> {
    if (this.saving() || this.selectedCount() === 0 || this.active()) return;
    this.saving.set(true);
    this.error.set('');
    try {
      await this.service.redeemWins(this.selectedIds());
      this.confirming.set(false);
      this.selectedIds.set([]);
      void this.router.navigate(['/gewinnspiele/gewinne/einloesung']);
    } catch {
      this.confirming.set(false);
      this.error.set('Die Gewinn-Einlösung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.');
      await this.refresh().catch(() => undefined);
    } finally { this.saving.set(false); }
  }

  private async refresh(): Promise<void> {
    const [wins, active] = await Promise.all([this.service.wins(), this.service.activeWinRedemption()]);
    this.wins.set(wins);
    this.active.set(active);
    this.selectedIds.update((ids) => ids.filter((id) => wins.some((win) => win.id === id && !win.redeemedAt)));
  }
}
