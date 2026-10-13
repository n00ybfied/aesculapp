import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { GamesService, CustomerGame } from '../../core/games/games.service';

@Component({
  selector: 'app-games',
  imports: [DatePipe, RouterLink],
  template: `
    <main class="mx-auto max-w-[500px] space-y-5 bg-background px-5 py-8">
      <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-bold text-primary">AKTIONEN</p><h1 class="text-3xl font-bold">Gewinnspiele</h1></div><a routerLink="/gewinnspiele/gewinne" class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-primary">Meine Gewinne</a></div>
      @if (loading()) { <div class="grid min-h-64 place-items-center" role="status">Lädt …</div> }
      @else {
        @if (error()) { <p class="rounded-xl bg-danger-soft p-4 text-danger" role="alert">{{ error() }}</p> }
        @for (game of games(); track game.id) {
          <article class="rounded-3xl border border-border bg-surface p-5 shadow-soft">
            <p class="text-sm font-bold text-primary">GLÜCKSRAD</p><h2 class="mt-1 text-xl font-bold">{{ game.title }}</h2>
            <p class="mt-2 text-sm text-muted">Bis {{ game.availableUntil | date:'dd.MM.yyyy HH:mm' }}</p>
            @if (game.status === 'sold_out') { <p class="mt-3 rounded-xl bg-warning-soft p-3 text-sm text-warning">{{ game.soldOutText }}</p> }
            @else { <p class="mt-3 text-sm text-muted">{{ game.remainingSpins }} {{ game.remainingSpins === 1 ? 'Teilnahme' : 'Teilnahmen' }} {{ game.participationMode === 'daily' ? 'heute' : 'noch' }} verfügbar</p> }
            <a [routerLink]="['/gewinnspiele', game.id]" class="mt-4 inline-flex min-h-12 items-center rounded-xl bg-primary px-5 font-bold text-on-primary">{{ game.status === 'sold_out' ? 'Ansehen' : 'Zum Glücksrad' }}</a>
          </article>
        } @empty { <p class="rounded-2xl border border-border bg-surface p-5 text-muted">Aktuell gibt es kein Gewinnspiel. Schauen Sie später wieder vorbei.</p> }
      }
    </main>
  `,
})
export class GamesPage {
  private readonly service = inject(GamesService);
  protected readonly games = signal<readonly CustomerGame[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  constructor() {
    void this.service.list().then((games) => this.games.set(games)).catch(() => this.error.set('Gewinnspiele konnten nicht geladen werden.')).finally(() => this.loading.set(false));
  }
}
