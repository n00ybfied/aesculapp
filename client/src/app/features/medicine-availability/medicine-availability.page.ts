import { Component, OnDestroy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CatalogueMedicine, MedicineCatalogueService, availabilityLabel } from '../../core/medicines/medicine-catalogue.service';

@Component({
  selector: 'app-medicine-availability-page',
  imports: [FormsModule],
  template: `
    <main class="px-5 pb-8 pt-6">
      <p class="text-sm font-semibold text-primary">APOTHEKE</p>
      <h1 class="mt-1 text-3xl font-bold">Medikamentenverfügbarkeit</h1>
      <p class="mt-2 text-sm leading-6 text-muted">Suchen Sie nach einem Medikamentennamen. Die Anzeige ist eine Momentaufnahme; fragen Sie bei dringendem Bedarf direkt in der Apotheke nach.</p>
      <label class="mt-6 block text-sm font-semibold" for="medicine-query">Medikament suchen</label>
      <input id="medicine-query" name="medicine-query" type="search" autocomplete="off" class="mt-2 min-h-12 w-full rounded-xl border border-border bg-surface px-4 outline-none focus:border-primary" [ngModel]="query()" (ngModelChange)="search($event)" placeholder="Name eingeben" />
      @if (loading()) { <p class="mt-4 text-sm text-muted" role="status">Suche läuft …</p> }
      @if (error()) { <p class="mt-4 rounded-xl border border-danger p-3 text-sm text-danger" role="alert">Die Suche konnte nicht geladen werden.</p> }
      @if (!loading() && !error() && !catalogueReady()) { <p class="mt-4 rounded-xl border border-border bg-accent p-3 text-sm text-muted" role="status">Der Medikamentenkatalog wurde auf diesem Server noch nicht eingerichtet.</p> }
      @if (!loading() && !error() && catalogueReady() && query().trim().length >= 2 && results().length === 0) { <p class="mt-4 text-sm text-muted">Keine passenden Medikamente gefunden.</p> }
      <ul class="mt-5 space-y-3" aria-label="Suchergebnisse">
        @for (medicine of results(); track medicine.pzn) {
          <li class="rounded-2xl border border-border bg-surface p-4">
            <p class="font-semibold">{{ medicine.name }}</p>
            @if (medicine.pack) { <p class="mt-1 text-sm text-muted">{{ medicine.pack }}</p> }
            <p class="mt-3 text-sm font-semibold" [class.text-success]="medicine.status === 'available'" [class.text-warning]="medicine.status === 'orderable'" [class.text-danger]="medicine.status === 'unavailable'" [class.text-muted]="medicine.status === 'unknown'">{{ label(medicine.status) }}</p>
          </li>
        }
      </ul>
    </main>
  `,
})
export class MedicineAvailabilityPage implements OnDestroy {
  private readonly catalogue = inject(MedicineCatalogueService);
  protected readonly query = signal('');
  protected readonly results = signal<readonly CatalogueMedicine[]>([]);
  protected readonly loading = signal(false);
  protected readonly error = signal(false);
  protected readonly catalogueReady = signal(true);
  protected readonly label = availabilityLabel;
  private timer?: ReturnType<typeof setTimeout>;
  private revision = 0;

  ngOnDestroy(): void { clearTimeout(this.timer); ++this.revision; }

  protected search(value: string): void {
    this.query.set(value);
    this.results.set([]);
    this.error.set(false);
    clearTimeout(this.timer);
    const revision = ++this.revision;
    if (value.trim().length < 2) { this.loading.set(false); return; }
    this.loading.set(true);
    this.timer = setTimeout(async () => {
      try { const result = await this.catalogue.searchWithStatus(value.trim()); if (revision === this.revision) { this.catalogueReady.set(result.catalogueReady); this.results.set(result.medicines); } }
      catch { if (revision === this.revision) this.error.set(true); }
      finally { if (revision === this.revision) this.loading.set(false); }
    }, 250);
  }
}
