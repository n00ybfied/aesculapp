import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AdminCoupon, AdminCouponPage, AdminCouponService } from '../../core/coupons/admin-coupon.service';

@Component({
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <section>
      <p class="eyebrow">GUTSCHEINE</p>
      <h2>Gutscheine</h2>
      <p><a class="create" routerLink="/gutscheine/neu">Neuen Gutschein anlegen</a></p>
      <form class="list-controls" (ngSubmit)="applyFilters()">
        <label>Suche nach Titel<input name="query" [(ngModel)]="query" placeholder="Titel eingeben" /></label>
        <label>Einträge pro Seite<select name="pageSize" [value]="pageSize" (change)="updatePageSize($event)"><option [value]="10">10</option><option [value]="25">25</option><option [value]="50">50</option><option value="all">Alle</option></select></label>
        <button type="submit">Filtern</button>
      </form>
      @if (loading()) {
        <p>Lädt …</p>
      }
      @if (!loading() && couponPage(); as page) {
      @for (coupon of page.coupons; track coupon.id) {
        <article>
          @if (coupon.imageUrl) {
            <img [src]="coupon.imageUrl" alt="" />
          }
          <div>
            <h3>{{ coupon.title }}</h3>
            <p>{{ coupon.subtitle }}</p>
            <small>{{ coupon.isVisible ? 'Sichtbar' : 'Ausgeblendet' }}</small>
          </div>
          <a class="edit" [routerLink]="['/gutscheine', coupon.id]">Bearbeiten</a>
          <button type="button" (click)="toggle(coupon)">
            {{ coupon.isVisible ? 'Ausblenden' : 'Einblenden' }}
          </button>
          <button class="delete" type="button" (click)="deleteCoupon(coupon)">Löschen</button>
        </article>
      } @empty {
        <p>{{ query.trim() ? 'Kein Gutschein passt zu Ihrer Suche.' : 'Noch keine Gutscheine angelegt.' }}</p>
      }
      @if (page.totalPages > 1) {
        <nav class="pagination" aria-label="Seitennavigation"><button type="button" [disabled]="page.page === 1" (click)="load(page.page - 1)">Zurück</button><span>Seite {{ page.page }} von {{ page.totalPages }} · {{ page.total }} Einträge</span><button type="button" [disabled]="page.page === page.totalPages" (click)="load(page.page + 1)">Weiter</button></nav>
      }
      }
      @if (error()) {
        <p class="error" role="alert">{{ error() }}</p>
      }
    </section>
  `,
  styles: [`
    :host { display: block; max-width: 52rem; }
    .eyebrow { color: var(--admin-primary); font-weight: 700; font-size: .8rem; }
    .create, .edit { display: inline-flex; min-height: 2.8rem; align-items: center; padding: 0 .9rem; border-radius: .45rem; font-weight: 700; text-decoration: none; cursor: pointer; }
    .create, button { background: var(--admin-primary); color: #fff; border: 0; }
    .edit { color: var(--admin-primary); border: 1px solid var(--admin-primary); }
    .list-controls { display: flex; align-items: end; gap: 1rem; margin-top: 1.5rem; }
    .list-controls label { display: grid; gap: .35rem; color: var(--admin-muted); font-size: .9rem; }
    .list-controls input, .list-controls select { min-height: 2.8rem; min-width: 14rem; padding: .5rem .65rem; border: 1px solid var(--admin-border); border-radius: .45rem; font: inherit; }
    .list-controls select { min-width: 8rem; }
    article { display: flex; align-items: center; gap: 1rem; margin-top: .75rem; padding: 1rem; border: 1px solid var(--admin-border); border-radius: .75rem; background: #fff; }
    article div { flex: 1; }
    h3, p { margin: 0; }
    small, p { color: var(--admin-muted); }
    img { width: 4rem; height: 4rem; object-fit: cover; border-radius: .5rem; }
    button { min-height: 2.8rem; padding: 0 .9rem; border-radius: .45rem; font-weight: 700; cursor: pointer; }
    .delete { background: var(--admin-danger); }
    .error { margin-top: 1rem; color: var(--admin-danger); }
    .pagination { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.25rem; }
    .pagination button:disabled { opacity: .45; }
    @media (max-width: 700px) { .list-controls, article { align-items: start; flex-direction: column; } }
  `],
})
export class AdminCouponsComponent {
  private readonly service = inject(AdminCouponService);
  protected readonly couponPage = signal<AdminCouponPage | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal('');
  protected query = '';
  protected pageSize: 10 | 25 | 50 | 'all' = 25;

  constructor() {
    void this.load();
  }

  protected async toggle(coupon: AdminCoupon): Promise<void> {
    try {
      await this.service.toggle(coupon);
      await this.load(this.couponPage()?.page ?? 1);
    } catch {
      this.error.set('Die Sichtbarkeit konnte nicht geändert werden.');
    }
  }

  protected async deleteCoupon(coupon: AdminCoupon): Promise<void> {
    if (!confirm(`„${coupon.title}“ wirklich löschen? Bereits zugehörige Einlösungen werden ebenfalls entfernt.`)) {
      return;
    }

    try {
      await this.service.delete(coupon.id);
      await this.load(this.couponPage()?.page ?? 1);
    } catch {
      this.error.set('Der Gutschein konnte nicht gelöscht werden.');
    }
  }

  protected applyFilters(): void {
    void this.load();
  }

  protected updatePageSize(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.pageSize = value === 'all' ? 'all' : Number(value) as 10 | 25 | 50;
    void this.load();
  }

  protected async load(page = 1): Promise<void> {
    this.loading.set(true);
    try {
      this.error.set('');
      this.couponPage.set(await this.service.list(page, this.pageSize, this.query.trim()));
    } catch {
      this.error.set('Gutscheine konnten nicht geladen werden.');
    } finally {
      this.loading.set(false);
    }
  }
}
