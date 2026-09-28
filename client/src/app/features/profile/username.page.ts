import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ProfileService } from '../../core/profile/profile.service';
import { PasswordVisibilityToggleComponent } from '../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-username-page',
  imports: [FormsModule, RouterLink, PasswordVisibilityToggleComponent],
  template: `
    @if (loading()) {
      <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center bg-background" aria-label="Kontodaten werden geladen"><span class="size-10 animate-spin rounded-full border-4 border-accent border-t-primary"></span></main>
    } @else {
      <main class="px-5 pb-8 pt-6">
        <a routerLink="/profil" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline underline-offset-4">Zur Kontoübersicht</a>
        <p class="mt-4 text-sm font-semibold text-primary">MEIN KONTO</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight">E-Mail-Adresse und Benutzernamen ändern</h1>
        <p class="mt-2 text-base text-muted">Beide Angaben sind immer gleich. Die Änderung wird erst nach Bestätigung des Links an Ihre neue E-Mail-Adresse wirksam. Danach melden Sie sich erneut an.</p>
        @if (loadError()) {
          <p class="mt-6 rounded-2xl border border-danger/40 bg-surface p-4 text-danger" role="alert">Das Profil konnte nicht geladen werden. Bitte versuchen Sie es erneut.</p>
        } @else {
          <form class="mt-7 rounded-3xl border border-border bg-surface p-5 shadow-card" (ngSubmit)="save()" novalidate>
            <label for="new-email" class="block text-sm font-medium">Neue E-Mail-Adresse und neuer Benutzername</label>
            <input id="new-email" class="mt-2 min-h-12 w-full rounded-xl border border-border bg-background px-4 text-foreground outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20" name="email" [(ngModel)]="email" type="email" required maxlength="100" autocomplete="email" />
            <div class="mt-5 text-sm font-medium">
              <label for="username-password">Aktuelles Passwort zur Bestätigung</label>
              <span class="password-field mt-2">
                <input #passwordInput id="username-password" class="min-h-12 w-full rounded-xl border border-border bg-background px-4 text-foreground" name="password" [(ngModel)]="password" type="password" autocomplete="current-password" required />
                <app-password-visibility-toggle [field]="passwordInput" />
              </span>
            </div>
            @if (error(); as message) { <p class="mt-4 text-sm text-danger" role="alert">{{ message }}</p> }
            @if (success(); as message) { <p class="mt-4 text-sm text-success" role="status">{{ message }} Bis zur Bestätigung bleiben Ihre bisherigen Anmeldedaten gültig.</p> }
            <button type="submit" class="mt-6 min-h-12 w-full rounded-xl bg-primary px-4 font-bold text-on-primary disabled:opacity-60" [disabled]="saving()">{{ saving() ? 'Wird gesendet …' : 'Bestätigungslink senden' }}</button>
          </form>
        }
      </main>
    }
  `,
})
export class UsernamePage {
  private readonly profiles = inject(ProfileService);
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected email = '';
  protected password = '';

  async ngOnInit(): Promise<void> {
    try {
      this.email = (await this.profiles.load()).email;
    } catch {
      this.loadError.set(true);
    } finally {
      this.loading.set(false);
    }
  }

  protected async save(): Promise<void> {
    if (this.saving()) return;
    this.error.set('');
    this.success.set('');
    const profile = this.profiles.profile();
    const email = this.email.trim().toLowerCase();
    if (!profile) return;
    if (email.length > 100 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      this.error.set('Bitte geben Sie eine gültige E-Mail-Adresse mit maximal 100 Zeichen ein.');
      return;
    }
    if (email === profile.email && email === profile.username) {
      this.error.set('Bitte geben Sie eine andere E-Mail-Adresse ein.');
      return;
    }
    if (!this.password) {
      this.error.set('Bitte bestätigen Sie die Änderung mit Ihrem aktuellen Passwort.');
      return;
    }
    this.saving.set(true);
    try {
      this.success.set(await this.profiles.requestEmailChange(email, this.password));
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Der Bestätigungslink konnte nicht versendet werden.');
    } finally {
      this.password = '';
      this.saving.set(false);
    }
  }
}
