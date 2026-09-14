import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface Medication {
  readonly id: number;
  readonly name: string;
  readonly dosage: string | null;
  readonly schedule: string | null;
  readonly notes: string | null;
  readonly refillDate: string | null;
  readonly hasImage: boolean;
  readonly updatedAt: string;
}

export interface MedicationDraft {
  readonly name: string;
  readonly dosage: string;
  readonly schedule: string;
  readonly notes: string;
  readonly refillDate: string;
  readonly image: File | null;
}

export interface MedicationList {
  readonly medications: readonly Medication[];
  readonly access: { readonly ownerId: number; readonly canManage: boolean };
}

@Injectable({ providedIn: 'root' })
export class MedicationService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL) + '/medications';
  private readonly auth = inject(AuthService);

  load(ownerId?: number): Promise<MedicationList> { const suffix = ownerId === undefined ? '' : `?ownerId=${encodeURIComponent(ownerId)}`; return firstValueFrom(this.http.get<MedicationList>(this.api + suffix, this.options())); }
  create(draft: MedicationDraft): Promise<Medication> { return firstValueFrom(this.http.post<{ medication: Medication }>(this.api, this.formData(draft), this.options())).then((response) => response.medication); }
  update(id: number, draft: MedicationDraft): Promise<Medication> { return firstValueFrom(this.http.post<{ medication: Medication }>(`${this.api}/${id}`, this.formData(draft), this.options())).then((response) => response.medication); }
  remove(id: number): Promise<void> { return firstValueFrom(this.http.delete<void>(`${this.api}/${id}`, this.options())); }
  image(id: number): Promise<Blob> { return firstValueFrom(this.http.get(`${this.api}/${id}/image`, { ...this.options(), responseType: 'blob' })); }

  private formData(draft: MedicationDraft): FormData {
    const form = new FormData();
    form.set('name', draft.name); form.set('dosage', draft.dosage); form.set('schedule', draft.schedule); form.set('notes', draft.notes); form.set('refillDate', draft.refillDate);
    if (draft.image !== null) form.set('image', draft.image, draft.image.name);
    return form;
  }

  private options(): { headers: HttpHeaders } { return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }) }; }
}
