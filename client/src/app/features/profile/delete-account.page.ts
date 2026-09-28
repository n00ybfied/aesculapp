import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ProfileService } from '../../core/profile/profile.service';
import { ConfirmDialogService } from '../../shared/feedback/confirm-dialog.service';
import { PasswordVisibilityToggleComponent } from '../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-delete-account-page',
  imports: [FormsModule, RouterLink, PasswordVisibilityToggleComponent],
  template: `
    <main class="px-5 pb-8 pt-6">
      <a routerLink="/profil" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline underline-offset-4">Zur Kontoübersicht</a>
      <p class="mt-4 text-sm font-semibold text-danger">MEIN KONTO</p>
      <h1 class="mt-1 text-3xl font-bold tracking-tight">Kundenzugang löschen</h1>
      <p class="mt-2 text-base text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</p>
      <form class="mt-7 rounded-3xl border border-danger/40 bg-surface p-5 shadow-card" (ngSubmit)="deleteAccount()" novalidate>
        <p class="text-sm leading-6 text-muted">Ihr Kundenzugang bei dieser Apotheke und seine Daten werden dauerhaft gelöscht – auch Termine, Chats, Medikamente, Gutscheine und Punkte. Andere Apotheken- und Mitarbeiterzugänge bleiben bestehen.</p>
        <div class="mt-5 text-sm font-medium">
          <label for="delete-account-password">Aktuelles Passwort</label>
          <span class="password-field mt-2">
            <input #passwordInput id="delete-account-password" class="min-h-12 w-full rounded-xl border border-border bg-background px-4 text-foreground" name="password" [(ngModel)]="password" type="password" autocomplete="current-password" required />
            <app-password-visibility-toggle [field]="passwordInput" />
          </span>
        </div>
        @if (error(); as message) { <p class="mt-4 text-sm text-danger" role="alert">{{ message }}</p> }
        <button type="submit" class="mt-6 min-h-12 w-full rounded-xl bg-danger px-4 font-bold text-white disabled:opacity-60" [disabled]="busy()">{{ busy() ? 'Wird gelöscht …' : 'Kundenzugang löschen' }}</button>
      </form>
    </main>
  `,
})
export class DeleteAccountPage {
  private readonly profiles = inject(ProfileService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly messages = inject(StatusMessageService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly busy = signal(false);
  protected readonly error = signal('');
  protected password = '';

  protected async deleteAccount(): Promise<void> {
    if (this.busy()) return;
    this.error.set('');
    if (!this.password) {
      this.error.set('Bitte geben Sie Ihr aktuelles Passwort ein.');
      return;
    }
    const password = this.password;
    const confirmed = await this.dialogs.confirm(
      'Ihr Kundenzugang bei dieser Apotheke und die zugehörigen Termine, Chats, Medikamente, Gutscheine und Punkte werden dauerhaft gelöscht. Andere Apotheken- oder Mitarbeiterzugänge bleiben bestehen. Möchten Sie fortfahren?',
      { title: 'Kundenzugang endgültig löschen?', confirmLabel: 'Kundenzugang löschen', destructive: true },
    );
    this.password = '';
    if (!confirmed) return;

    this.busy.set(true);
    try {
      await this.profiles.deleteAccount(password);
      this.auth.logout();
      await this.router.navigateByUrl('/login');
      this.messages.show('Ihr Kundenzugang wurde gelöscht.', { kind: 'success' });
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Der Kundenzugang konnte nicht gelöscht werden. Bitte versuchen Sie es erneut.');
    } finally {
      this.busy.set(false);
    }
  }
}
