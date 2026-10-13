import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AdminGame, AdminGamesService } from '../../core/games/admin-games.service';

@Component({
  selector: 'app-admin-games',
  imports: [DatePipe, RouterLink],
  template: `
    <section class="games-page">
      <div class="heading"><div><p class="eyebrow">AKTIONEN</p><h2>Gewinnspiele</h2></div><a class="primary" routerLink="/gewinnspiele/neu">Neues Glücksrad</a></div>
      <p>Teilnahmen und Gewinne werden serverseitig gezogen und gespeichert.</p>
      @if (loading()) { <p>Lädt …</p> }
      @if (error()) { <p class="error" role="alert">{{ error() }}</p> }
      <div class="list">
        @for (game of games(); track game.id) {
          <article>
            <div><h3>{{ game.title }}</h3><p>{{ statusLabel(game) }} · {{ game.availableFrom | date:'dd.MM.yyyy HH:mm' }} – {{ game.availableUntil | date:'dd.MM.yyyy HH:mm' }}</p></div>
            <a [routerLink]="['/gewinnspiele', game.id]">Bearbeiten und Gewinne</a>
          </article>
        } @empty { @if (!loading()) { <p>Noch kein Gewinnspiel angelegt.</p> } }
      </div>
    </section>
  `,
  styles: [`
    :host{display:block}.games-page{max-width:60rem}.heading{display:flex;justify-content:space-between;align-items:center;gap:1rem}.eyebrow{color:var(--admin-primary);font-size:.8rem;font-weight:700}h2{margin:.2rem 0 0}p{color:var(--admin-muted)}.primary,a{display:inline-flex;align-items:center;min-height:2.7rem;border-radius:.55rem;padding:0 1rem;font-weight:700;text-decoration:none;color:var(--admin-primary);border:1px solid var(--admin-border)}.primary{background:var(--admin-primary);color:#fff}.list{display:grid;gap:.7rem;margin-top:1.3rem}article{display:flex;justify-content:space-between;align-items:center;gap:1rem;background:#fff;border:1px solid var(--admin-border);border-radius:.8rem;padding:1rem}article h3{margin:0}article p{margin:.3rem 0 0}.error{color:var(--admin-danger)}@media(max-width:700px){.heading,article{align-items:start;flex-direction:column}}
  `],
})
export class AdminGamesComponent {
  private readonly service = inject(AdminGamesService);
  protected readonly games = signal<readonly AdminGame[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  constructor() {
    void this.service.list().then((games) => this.games.set(games)).catch(() => this.error.set('Die Gewinnspiele konnten nicht geladen werden.')).finally(() => this.loading.set(false));
  }

  protected statusLabel(game: AdminGame): string {
    return ({ draft: 'Entwurf', scheduled: 'Geplant', active: 'Aktiv', sold_out: 'Ausverkauft', ended: 'Beendet' } as const)[game.status];
  }
}
