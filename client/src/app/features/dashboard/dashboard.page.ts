import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-dashboard-page',
  template: `
    <main class="grid min-h-dvh place-items-center bg-background p-6 text-foreground">
      <section class="w-full max-w-md rounded-3xl border border-border bg-surface p-8 text-center shadow-card">
        <p class="text-sm font-semibold tracking-wide text-primary">ANMELDUNG ERFOLGREICH</p>
        <h1 class="mt-3 text-3xl font-bold">Hallo, {{ authService.currentUser()?.displayName }}!</h1>
        <p class="mt-3 text-muted">Das Dashboard bauen wir im nächsten Schritt aus.</p>
        <button
          type="button"
          class="mt-8 min-h-12 w-full rounded-xl border border-border px-5 font-semibold transition hover:bg-accent focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary"
          (click)="logout()"
        >
          Abmelden
        </button>
      </section>
    </main>
  `,
})
export class DashboardPage {
  protected readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected logout(): void {
    this.authService.logout();
    void this.router.navigate(['/login']);
  }
}
