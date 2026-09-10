import { DOCUMENT } from '@angular/common';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { firstValueFrom, timeout } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface TenantBranding { readonly logoUrl: string | null; readonly squareLogoUrl: string | null; readonly faviconUrl: string | null; readonly initialPoints: number; readonly birthdayBonusPoints: number; readonly pointsPerEuro: number; readonly allowDuplicateReceiptImports: boolean; readonly showCustomerDebugOutput: boolean; readonly smtpHost: string | null; readonly smtpPort: number | null; readonly smtpEncryption: 'tls' | 'ssl' | 'none' | null; readonly smtpUsername: string | null; readonly smtpFrom: string | null; readonly smtpPasswordConfigured: boolean; }

@Injectable({ providedIn: 'root' })
export class TenantBrandingService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly document = inject(DOCUMENT);
  private readonly brandingState = signal<TenantBranding>({ logoUrl: null, squareLogoUrl: null, faviconUrl: null, initialPoints: 0, birthdayBonusPoints: 200, pointsPerEuro: 10, allowDuplicateReceiptImports: false, showCustomerDebugOutput: false, smtpHost: null, smtpPort: 587, smtpEncryption: 'tls', smtpUsername: null, smtpFrom: null, smtpPasswordConfigured: false });

  readonly branding = this.brandingState.asReadonly();

  async get(): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.get<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', { headers: this.headers() })));
  }

  async getPublic(): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.get<{ branding: TenantBranding }>(this.api() + '/branding').pipe(timeout(3500))));
  }

  async initializePublicBranding(): Promise<void> {
    try {
      await this.getPublic();
    } catch {
      // The local favicon in index.html remains the fallback while the public branding API is unavailable.
    }
  }

  async update(data: FormData): Promise<TenantBranding> {
    return this.store(await firstValueFrom(this.http.post<{ branding: TenantBranding }>(this.api() + '/admin/settings/branding', data, { headers: this.headers() })));
  }

  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private store(response: { readonly branding: TenantBranding }): TenantBranding {
    this.brandingState.set(response.branding);
    this.applyFavicon(response.branding.faviconUrl);
    return response.branding;
  }

  private applyFavicon(faviconUrl: string | null): void {
    if (faviconUrl === null) {
      return;
    }

    this.document.querySelector<HTMLLinkElement>('#app-favicon')?.setAttribute('href', faviconUrl);
  }

  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
