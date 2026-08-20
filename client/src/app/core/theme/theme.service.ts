import { DOCUMENT } from '@angular/common';
import { Injectable, inject } from '@angular/core';
import { activeTheme, type ThemeDefinition } from './theme.config';

@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);

  readonly activeTheme: ThemeDefinition = activeTheme;

  initialize(): void {
    this.document.documentElement.dataset['theme'] = this.activeTheme.id;
    this.applyThemeIcons();
  }

  private applyThemeIcons(): void {
    const favicon = this.document.querySelector<HTMLLinkElement>('#app-favicon');
    const appleTouchIcon = this.document.querySelector<HTMLLinkElement>('#app-apple-touch-icon');

    favicon?.setAttribute('href', this.activeTheme.faviconPath);
    appleTouchIcon?.setAttribute('href', this.activeTheme.faviconPath);
  }
}
