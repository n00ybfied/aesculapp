import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export type MedicineAvailability = 'available' | 'orderable' | 'unavailable' | 'unknown';

export interface CatalogueMedicine {
  readonly pzn: string;
  readonly name: string;
  readonly pack: string;
  readonly status: MedicineAvailability;
}

export interface MedicineSearchResult {
  readonly medicines: readonly CatalogueMedicine[];
  readonly catalogueReady: boolean;
}

export function availabilityLabel(status: MedicineAvailability): string {
  switch (status) {
    case 'available': return 'In der Apotheke vorhanden';
    case 'orderable': return 'Derzeit nicht vorhanden, voraussichtlich lieferbar';
    case 'unavailable': return 'Derzeit nicht vorhanden und nicht lieferbar';
    case 'unknown': return 'Verfügbarkeit derzeit nicht verlässlich abrufbar';
  }
}

@Injectable({ providedIn: 'root' })
export class MedicineCatalogueService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly api = inject(API_BASE_URL) + '/medicines/search';

  async search(query: string): Promise<readonly CatalogueMedicine[]> {
    const result = await this.searchWithStatus(query);
    if (!result.catalogueReady) throw new Error('Catalogue has not been imported.');
    return result.medicines;
  }

  searchWithStatus(query: string): Promise<MedicineSearchResult> {
    const headers = new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` });
    return firstValueFrom(this.http.post<MedicineSearchResult>(this.api, { q: query }, { headers }));
  }
}
