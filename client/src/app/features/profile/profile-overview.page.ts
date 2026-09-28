import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { ProfileService } from '../../core/profile/profile.service';

@Component({
  selector: 'app-profile-overview-page',
  imports: [RouterLink, NgIcon],
  template: `
    @if (loading()) {
      <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center bg-background" aria-label="Kontoübersicht wird geladen"><span class="size-10 animate-spin rounded-full border-4 border-accent border-t-primary"></span></main>
    } @else {
      <main class="px-5 pb-8 pt-6">
        <p class="text-sm font-semibold text-primary">MEIN KONTO</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">Kontoübersicht</h1>
        @if (loadError()) {
          <p class="mt-6 rounded-2xl border border-danger/40 bg-surface p-4 text-danger" role="alert">Das Profil konnte nicht geladen werden. Bitte versuchen Sie es erneut.</p>
        } @else {
          <p class="mt-2 text-base text-muted">{{ profiles.profile()?.displayName }} · {{ profiles.profile()?.email }}</p>
        }
        <nav class="mt-7 space-y-3" aria-label="Konto verwalten">
          <a routerLink="/profil/bearbeiten" class="flex min-h-20 items-center gap-4 rounded-2xl border border-border bg-surface p-4 shadow-card transition hover:border-primary focus-visible:outline-3 focus-visible:outline-primary">
            <ng-icon name="lucideUserRound" size="1.5rem" class="text-primary" aria-hidden="true" />
            <span class="min-w-0 flex-1"><span class="block font-bold">Profil bearbeiten</span><span class="mt-1 block text-sm text-muted">Persönliche Daten, Benachrichtigungen und Schnellzugriffe</span></span>
            <ng-icon name="lucideChevronRight" size="1.25rem" aria-hidden="true" />
          </a>
          <a routerLink="/profil/benutzername" class="flex min-h-20 items-center gap-4 rounded-2xl border border-border bg-surface p-4 shadow-card transition hover:border-primary focus-visible:outline-3 focus-visible:outline-primary">
            <ng-icon name="lucideUserRound" size="1.5rem" class="text-primary" aria-hidden="true" />
            <span class="min-w-0 flex-1"><span class="block font-bold">E-Mail-Adresse und Benutzernamen ändern</span><span class="mt-1 block text-sm text-muted">Neue Adresse per E-Mail bestätigen</span></span>
            <ng-icon name="lucideChevronRight" size="1.25rem" aria-hidden="true" />
          </a>
          <a routerLink="/profil/konto-loeschen" class="flex min-h-20 items-center gap-4 rounded-2xl border border-danger/30 bg-surface p-4 shadow-card transition hover:border-danger focus-visible:outline-3 focus-visible:outline-danger">
            <ng-icon name="lucideCircleAlert" size="1.5rem" class="text-danger" aria-hidden="true" />
            <span class="min-w-0 flex-1"><span class="block font-bold">Kundenzugang löschen</span><span class="mt-1 block text-sm text-muted">Konto bei dieser Apotheke dauerhaft entfernen</span></span>
            <ng-icon name="lucideChevronRight" size="1.25rem" aria-hidden="true" />
          </a>
        </nav>
      </main>
    }
  `,
})
export class ProfileOverviewPage {
  protected readonly profiles = inject(ProfileService);
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);

  async ngOnInit(): Promise<void> {
    try {
      await this.profiles.load();
    } catch {
      this.loadError.set(true);
    } finally {
      this.loading.set(false);
    }
  }
}
