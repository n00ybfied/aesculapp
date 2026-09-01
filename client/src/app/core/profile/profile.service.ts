import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface CustomerProfile {
  readonly id: number;
  readonly username: string;
  readonly email: string;
  readonly displayName: string;
  readonly phone: string | null;
  readonly streetAddress: string | null;
  readonly postalCode: string | null;
  readonly city: string | null;
  readonly profileImageUrl: string | null;
}

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);
  readonly profile = signal<CustomerProfile | null>(null);

  async load(): Promise<CustomerProfile> {
    const response = await firstValueFrom(this.http.get<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile', { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  async save(profile: Pick<CustomerProfile, 'displayName' | 'phone' | 'streetAddress' | 'postalCode' | 'city'>): Promise<CustomerProfile> {
    const response = await firstValueFrom(this.http.patch<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile', profile, { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  async uploadPhoto(photo: Blob): Promise<CustomerProfile> {
    const body = new FormData();
    body.append('photo', photo, 'profilbild.jpg');
    const response = await firstValueFrom(this.http.post<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile/photo', body, { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  private headers(): HttpHeaders {
    const token = this.auth.accessToken();
    return token === null ? new HttpHeaders() : new HttpHeaders({ Authorization: 'Bearer ' + token });
  }
}
