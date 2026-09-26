import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-appointment-tabs-page',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <section class="mx-auto max-w-xl px-5 py-6">
      <p class="text-sm font-semibold text-primary">IHRE APOTHEKE</p>
      <h1 class="mt-1 text-2xl font-bold">Termine</h1>
      <nav class="mt-6 grid grid-cols-2 gap-2 rounded-2xl bg-accent p-1" aria-label="Terminansichten">
        <a routerLink="/termine" routerLinkActive="bg-surface text-primary shadow-soft" [routerLinkActiveOptions]="{ exact: true }" ariaCurrentWhenActive="page" class="flex min-h-12 items-center justify-center rounded-xl px-2 text-center text-sm font-bold text-muted">Neuer Termin</a>
        <a routerLink="/termine/meine" routerLinkActive="bg-surface text-primary shadow-soft" ariaCurrentWhenActive="page" class="flex min-h-12 items-center justify-center rounded-xl px-2 text-center text-sm font-bold text-muted">Meine Termine</a>
      </nav>
      <router-outlet />
    </section>
  `,
})
export class AppointmentTabsPage {}
