import { Component, inject, signal } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ThemeService } from '../../core/theme/theme.service';

@Component({
  selector: 'app-website-page',
  template: `
    <main class="min-h-[calc(100dvh-8.5rem)] bg-background">
      @if (websiteUrl; as url) {
        @if (!toolbarDismissed()) {
          <header class="flex items-center justify-between gap-3 border-b border-border bg-surface px-5 py-3">
            <div><p class="text-sm font-semibold text-primary">IHRE APOTHEKE</p><h1 class="mt-1 text-xl font-bold">Webseite</h1></div>
            <div class="flex items-center gap-2">
              <a [href]="url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-border px-4 text-sm font-semibold hover:bg-accent">Extern öffnen</a>
              <button type="button" class="grid size-11 place-items-center rounded-xl text-muted hover:bg-accent hover:text-foreground" (click)="toolbarDismissed.set(true)" aria-label="Kopfzeile ausblenden">×</button>
            </div>
          </header>
        }
        <iframe class="block w-full border-0 bg-surface" [style.height]="toolbarDismissed() ? 'calc(100dvh - 8.5rem)' : 'calc(100dvh - 12rem)'" [src]="safeWebsiteUrl" title="Webseite Ihrer Apotheke" referrerpolicy="strict-origin-when-cross-origin"></iframe>
      } @else {
        <section class="grid min-h-[calc(100dvh-8.5rem)] place-items-center px-5 text-center"><div><h1 class="text-xl font-bold">Webseite noch nicht eingerichtet</h1><p class="mt-2 text-sm leading-6 text-muted">Ihre Apotheke hat noch keine Webseite für die App hinterlegt.</p></div></section>
      }
    </main>
  `,
})
export class WebsitePage {
  private readonly sanitizer = inject(DomSanitizer);
  private readonly theme = inject(ThemeService).activeTheme;
  protected readonly toolbarDismissed = signal(false);
  protected readonly websiteUrl = this.theme.websiteUrl;
  protected readonly safeWebsiteUrl: SafeResourceUrl | null = this.websiteUrl === null ? null : this.sanitizer.bypassSecurityTrustResourceUrl(this.websiteUrl);
}
