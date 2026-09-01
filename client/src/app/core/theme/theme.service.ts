import { DOCUMENT } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom, timeout } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { activeTheme, type ThemeDefinition } from './theme.config';

interface BrandingResponse {
  readonly branding: {
    readonly logoUrl: string | null;
    readonly squareLogoUrl: string | null;
    readonly faviconUrl: string | null;
  };
}

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private theme: ThemeDefinition = { ...activeTheme };

  get activeTheme(): ThemeDefinition {
    return this.theme;
  }

  async initialize(): Promise<void> {
    this.document.documentElement.dataset['theme'] = this.theme.id;

    try {
      const { branding } = await firstValueFrom(
        this.http.get<BrandingResponse>(`${this.apiBaseUrl}/branding`).pipe(timeout(3500)),
      );
      this.theme = {
        ...activeTheme,
        logoPath: branding.logoUrl ?? activeTheme.logoPath,
        squareLogoPath: branding.squareLogoUrl ?? activeTheme.squareLogoPath,
        faviconPath: branding.faviconUrl ?? activeTheme.faviconPath,
      };
    } catch {
      // The shipped fallback branding keeps the app usable while the API is unavailable.
      this.theme = { ...activeTheme };
    }

    this.applyThemeIcons();
  }

  private applyThemeIcons(): void {
    const favicon = this.document.querySelector<HTMLLinkElement>('#app-favicon');
    const appleTouchIcon = this.document.querySelector<HTMLLinkElement>('#app-apple-touch-icon');

    favicon?.setAttribute('href', this.theme.faviconPath);
    appleTouchIcon?.setAttribute('href', this.theme.squareLogoPath);
  }
}
