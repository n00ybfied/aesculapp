import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export type ContactDay = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday';
export type OpeningHours = Record<ContactDay, string>;
export interface PharmacyContact { readonly address: string | null; readonly phone: string | null; readonly email: string | null; readonly openingHours: OpeningHours; readonly latitude: number | null; readonly longitude: number | null; readonly mapZoom: number; readonly googleMapsUrl: string | null; readonly additionalHtml: string; }

@Injectable({ providedIn: 'root' })
export class PharmacyContactService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  get(): Promise<PharmacyContact> {
    return firstValueFrom(this.http.get<{ contact: PharmacyContact }>(`${this.apiBaseUrl}/contact`, { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) })).then((response) => response.contact);
  }
}
