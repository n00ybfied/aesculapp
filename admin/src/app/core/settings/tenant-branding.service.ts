import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface TenantBranding { readonly logoUrl: string | null; readonly squareLogoUrl: string | null; readonly faviconUrl: string | null; }

@Injectable({ providedIn: 'root' })
export class TenantBrandingService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  async get(): Promise<TenantBranding> { return (await firstValueFrom(this.http.get<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', { headers: this.headers() }))).branding; }
  async update(data: FormData): Promise<TenantBranding> { return (await firstValueFrom(this.http.post<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', data, { headers: this.headers() }))).branding; }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
