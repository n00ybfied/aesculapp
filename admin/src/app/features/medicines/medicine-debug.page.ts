import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminMedicineDebugService, MedicineDebugResponse } from '../../core/medicines/admin-medicine-debug.service';

@Component({
  selector: 'app-medicine-debug',
  imports: [FormsModule],
  template: `
    <section class="card">
      <p class="eyebrow">INTERNE PRÜFUNG</p>
      <h2>Medikamentendaten prüfen</h2>
      <p>Diese Rohdaten dienen nur der Prüfung des Dateiformats. Preise und genaue Bestände erscheinen nicht in der Kunden-App.</p>
      <label for="medicine-debug-query">Medikament oder PZN</label>
      <div class="search-row"><input id="medicine-debug-query" type="search" [(ngModel)]="query" (keyup.enter)="inspect()" /><button type="button" (click)="inspect()">Prüfen</button></div>
      @if (error()) { <p role="alert" class="error">Daten konnten nicht geladen werden.</p> }
      @if (result(); as data) {
        <h3>Importstatus</h3>
        @for (source of importKeys(data); track source) {
          <p><strong>{{ source }}:</strong> @if (data.imports[source]; as info) { {{ info.row_count }} Einträge · Quelldatum {{ info.source_mtime }} · importiert {{ info.imported_at }} } @else { Noch nicht importiert }</p>
        }
        <div class="table-scroll"><table><thead><tr><th>PZN</th><th>Name</th><th>Packung</th><th>Edition</th><th>Liefercode</th><th>Bestand</th><th>Reserviert*</th><th>AEP</th><th>KVP</th><th>AVP</th></tr></thead><tbody>
          @for (row of data.rows; track row.pzn) { <tr><td>{{ row.pzn }}</td><td>{{ row.name }}</td><td>{{ row.pack }}</td><td>{{ row.edition }}</td><td>{{ row.delivery_code || 'leer' }}</td><td>{{ row.stock_qty ?? '–' }}</td><td>{{ row.reserved_qty ?? '–' }}</td><td>{{ euro(row.aep_cents) }}</td><td>{{ euro(row.kvp_cents) }}</td><td>{{ euro(row.avp_cents) }}</td></tr> }
        </tbody></table></div>
        <p class="hint">*Die Bedeutung des dritten ApoScout-Werts ist noch nicht bestätigt. Leerer Liefercode wird vorläufig als lieferbar behandelt. Veraltete Bestände gelten in der Kunden-App als unbekannt.</p>
      }
    </section>
  `,
  styles: [`
    .card{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:18px;padding:24px}
    h2{margin:4px 0 12px}h3{margin-top:24px}label{display:block;font-weight:700;margin-top:24px}
    .search-row{display:flex;gap:8px;margin-top:8px}.search-row input{min-width:0;flex:1;min-height:44px;padding:8px}.search-row button{min-height:44px;padding:8px 16px}
    .table-scroll{overflow-x:auto;margin-top:16px}table{border-collapse:collapse;min-width:800px;width:100%}th,td{padding:8px;text-align:left;border-bottom:1px solid var(--admin-border);white-space:nowrap}
    .hint{margin-top:20px;font-size:.875rem}.error{color:var(--admin-danger)}
  `],
})
export class MedicineDebugPage {
  private readonly service = inject(AdminMedicineDebugService);
  protected query = '';
  protected readonly result = signal<MedicineDebugResponse | null>(null);
  protected readonly error = signal(false);
  protected readonly importKeys = (result: MedicineDebugResponse) => Object.keys(result.imports);
  protected readonly euro = (cents: number | null) => cents === null ? '–' : `${(cents / 100).toFixed(2)} €`;

  protected async inspect(): Promise<void> {
    this.error.set(false);
    try { this.result.set(await this.service.inspect(this.query.trim())); }
    catch { this.error.set(true); }
  }
}
