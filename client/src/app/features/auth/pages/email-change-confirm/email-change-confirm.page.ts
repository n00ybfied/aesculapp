import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-email-change-confirm-page',
  imports: [RouterLink],
  template: `
    <main class="flex min-h-dvh flex-col justify-center bg-background px-5 py-8 text-foreground">
      <section class="mx-auto w-full max-w-[500px] text-center">
        <img [src]="theme.logoPath" width="530" height="174" class="mx-auto mb-8 h-auto w-52" [alt]="theme.pharmacyName" />
        <div class="rounded-3xl border border-border bg-surface p-6 shadow-card sm:p-8">
          @if (status() === 'checking') {
            <h1 class="text-2xl font-bold">Neue E-Mail-Adresse wird bestätigt …</h1>
          } @else if (status() === 'success') {
            <h1 class="text-2xl font-bold">E-Mail-Adresse geändert</h1>
            <p class="mt-3 text-muted">Melden Sie sich jetzt mit Ihrer neuen E-Mail-Adresse an.</p>
            <a routerLink="/login" class="mt-6 inline-flex min-h-12 items-center rounded-xl bg-primary px-5 font-bold text-on-primary">Zur Anmeldung</a>
          } @else {
            <h1 class="text-2xl font-bold">Link ungültig oder abgelaufen</h1>
            <p class="mt-3 text-muted">Fordern Sie im Profil einen neuen Bestätigungslink an.</p>
            <a routerLink="/profil" class="mt-6 inline-flex min-h-12 items-center rounded-xl bg-primary px-5 font-bold text-on-primary">Zum Profil</a>
            <a routerLink="/login" class="mt-6 ml-3 inline-flex min-h-12 items-center rounded-xl border border-border px-5 font-bold">Zur Anmeldung</a>
          }
        </div>
      </section>
    </main>
  `,
})
export class EmailChangeConfirmPage {
  private readonly auth = inject(AuthService);
  private readonly token = inject(ActivatedRoute).snapshot.queryParamMap.get('token');
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly status = signal<'checking' | 'success' | 'invalid'>('checking');

  constructor() {
    if (this.token === null) this.status.set('invalid');
    else void this.auth.confirmEmailChange(this.token).then((success) => this.status.set(success ? 'success' : 'invalid'));
  }
}
