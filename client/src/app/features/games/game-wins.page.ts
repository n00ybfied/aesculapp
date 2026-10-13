import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { GamesService, GameSpin } from '../../core/games/games.service';

@Component({
  selector: 'app-game-wins',
  imports: [DatePipe, RouterLink],
  template: `
    <main class="mx-auto max-w-[500px] space-y-5 bg-background px-5 py-8">
      <a routerLink="/gewinnspiele" class="text-sm font-semibold text-primary">← Gewinnspiele</a>
      <h1 class="text-3xl font-bold">Meine Gewinne</h1>
      @if (loading()) { <div class="grid min-h-64 place-items-center" role="status">Lädt …</div> }
      @else {
        @if (error()) { <p class="rounded-xl bg-danger-soft p-4 text-danger" role="alert">{{ error() }}</p> }
        @for (win of wins(); track win.id) {
          <article class="rounded-3xl border border-border bg-surface p-5 shadow-soft">
            <p class="text-sm font-bold text-primary">{{ win.gameTitle }}</p><h2 class="mt-1 text-xl font-bold">{{ win.title }}</h2>
            <p class="mt-2 text-sm text-muted">Gewonnen am {{ win.createdAt | date:'dd.MM.yyyy HH:mm' }}</p>
            @if (win.redeemedAt) { <p class="mt-3 font-semibold text-success">Eingelöst am {{ win.redeemedAt | date:'dd.MM.yyyy' }}</p> }
            @else { <p class="mt-3 text-sm text-muted">Zeigen Sie diesen Code in der Apotheke vor:</p><p class="mt-1 select-all rounded-xl bg-accent p-3 text-center font-mono text-xl font-bold tracking-widest">{{ win.couponCode }}</p> }
          </article>
        } @empty { <p class="rounded-2xl border border-border bg-surface p-5 text-muted">Noch keine Gutscheine gewonnen.</p> }
      }
    </main>
  `,
})
export class GameWinsPage {
  private readonly service = inject(GamesService);
  protected readonly wins = signal<readonly GameSpin[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  constructor() {
    void this.service.wins().then((wins) => this.wins.set(wins)).catch(() => this.error.set('Gewinne konnten nicht geladen werden.')).finally(() => this.loading.set(false));
  }
}
