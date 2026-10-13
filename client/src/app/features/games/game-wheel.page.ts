import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { CustomerGame, GameSpin, GamesService } from '../../core/games/games.service';

interface WheelSegment {
  label: string;
  start: number;
  end: number;
  center: number;
  x: number;
  y: number;
  color: string;
}

@Component({
  selector: 'app-game-wheel',
  imports: [DatePipe, RouterLink],
  templateUrl: './game-wheel.page.html',
  styleUrl: './game-wheel.page.css',
})
export class GameWheelPage {
  private readonly service = inject(GamesService);
  private readonly route = inject(ActivatedRoute);
  private readonly id = Number(this.route.snapshot.paramMap.get('id'));
  private requestId: string | null = null;
  private readonly colors = [
    'var(--color-primary)', 'var(--color-warning)', 'var(--color-success)',
    'var(--color-accent-foreground)', 'var(--color-primary-strong)',
  ];

  protected readonly game = signal<CustomerGame | null>(null);
  protected readonly spins = signal<readonly GameSpin[]>([]);
  protected readonly loading = signal(true);
  protected readonly spinning = signal(false);
  protected readonly result = signal<GameSpin | null>(null);
  protected readonly error = signal('');
  protected readonly rotation = signal(0);
  protected readonly durationMs = signal(0);
  protected readonly segments = computed((): WheelSegment[] => {
    const game = this.game();
    if (!game?.prizes) return [];
    const segments: WheelSegment[] = [];
    let start = 0;
    for (const [index, prize] of game.prizes.entries()) {
      const width = prize.probabilityBp * 360 / 10000;
      segments.push(this.segment(prize.wheelLabel, start, start + width, this.colors[index % this.colors.length]));
      start += width;
    }
    if (game.noWinEnabled && start < 360) segments.push(this.segment('Leider nein', start, 360, 'var(--color-danger)'));
    return segments;
  });
  protected readonly wheelBackground = computed(() => {
    const stops = this.segments().map((segment) => `${segment.color} ${segment.start.toFixed(3)}deg ${segment.end.toFixed(3)}deg`);
    return stops.length ? `conic-gradient(${stops.join(',')})` : 'var(--color-accent)';
  });
  protected readonly noWinPercent = computed(() => (10000 - (this.game()?.prizes ?? []).reduce((sum, prize) => sum + prize.probabilityBp, 0)) / 100);

  constructor() {
    void this.load();
  }

  protected async spin(): Promise<void> {
    const game = this.game();
    if (!game || game.status !== 'active' || game.remainingSpins < 1 || this.spinning()) return;
    this.spinning.set(true);
    this.error.set('');
    this.result.set(null);
    this.requestId ??= crypto.randomUUID();
    try {
      const response = await this.service.spin(game.id, this.requestId);
      this.requestId = null;
      this.game.set(response.game);
      this.spins.update((items) => [response.spin, ...items]);
      const index = response.spin.wheelIndex ?? this.segments().length - 1;
      const segment = this.segments()[index];
      if (!segment) throw new Error('Gewinnfeld fehlt.');
      const target = (360 - segment.center) % 360;
      const current = ((this.rotation() % 360) + 360) % 360;
      const delta = (target - current + 360) % 360;
      const duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 5000;
      this.durationMs.set(duration);
      await new Promise<void>((resolve) => requestAnimationFrame(() => {
        this.rotation.update((value) => value + 6 * 360 + delta);
        setTimeout(resolve, duration);
      }));
      this.result.set(response.spin);
    } catch (error: unknown) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
        ? error.error.message : 'Das Glücksrad konnte nicht gedreht werden. Bitte versuchen Sie es erneut.');
      try { await this.load(false); } catch { /* The visible error stays in place. */ }
    } finally { this.spinning.set(false); }
  }

  private segment(label: string, start: number, end: number, color: string): WheelSegment {
    const center = (start + end) / 2;
    const angle = (center - 90) * Math.PI / 180;
    return { label, start, end, center, color, x: 50 + Math.cos(angle) * 31, y: 50 + Math.sin(angle) * 31 };
  }

  private async load(showLoading = true): Promise<void> {
    if (!Number.isInteger(this.id) || this.id < 1) { this.error.set('Gewinnspiel nicht gefunden.'); this.loading.set(false); return; }
    if (showLoading) this.loading.set(true);
    try {
      const response = await this.service.detail(this.id);
      this.game.set(response.game);
      this.spins.set(response.spins);
    } catch {
      this.error.set('Das Gewinnspiel konnte nicht geladen werden.');
    } finally { this.loading.set(false); }
  }
}
