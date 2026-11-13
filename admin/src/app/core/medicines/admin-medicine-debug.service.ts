import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface MedicineDebugRow {
  readonly pzn: string;
  readonly name: string;
  readonly pack: string;
  readonly delivery_code: string;
  readonly edition: string;
  readonly aep_cents: number | null;
  readonly kvp_cents: number | null;
  readonly avp_cents: number | null;
  readonly stock_qty: number | null;
  readonly reserved_qty: number | null;
}

export interface MedicineDebugResponse {
  readonly rows: readonly MedicineDebugRow[];
  readonly imports: Record<string, { imported_at: string; source_mtime: string | null; row_count: number; sha256: string } | null>;
}

@Injectable({ providedIn: 'root' })
export class AdminMedicineDebugService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1'
    : location.hostname.endsWith('stadtapotheke-trofaiach.at')
      ? 'https://api.stadtapotheke-trofaiach.at/api/v1'
      : 'https://api.aesculapp.floatbox.at/api/v1';

  inspect(query: string): Promise<MedicineDebugResponse> {
    return firstValueFrom(this.http.get<MedicineDebugResponse>(`${this.api}/admin/medicines/debug`, {
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }), params: { q: query },
    }));
  }
}
