import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface TenantBranding { readonly logoUrl: string | null; readonly squareLogoUrl: string | null; readonly faviconUrl: string | null; }

@Injectable({ providedIn: 'root' })
export class TenantBrandingService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly brandingState = signal<TenantBranding>({ logoUrl: null, squareLogoUrl: null, faviconUrl: null });

  readonly branding = this.brandingState.asReadonly();

  async get(): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.get<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', { headers: this.headers() })));
  }

  async getPublic(): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.get<{ branding: TenantBranding }>(this.api() + '/branding')));
  }

  async update(data: FormData): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.post<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', data, { headers: this.headers() })));
  }

  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private store(response: { readonly branding: TenantBranding }): TenantBranding { this.brandingState.set(response.branding); return response.branding; }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
