import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

interface PlaceholderData {
  readonly title: string;
  readonly description: string;
}

@Component({
  selector: 'app-placeholder-page',
  imports: [RouterLink],
  template: `
    <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center px-5 py-8">
      <section class="w-full rounded-3xl border border-border bg-surface p-7 text-center shadow-card">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-accent text-xl font-bold text-primary">A</span>
        <p class="mt-5 text-sm font-bold tracking-wide text-primary">IN VORBEREITUNG</p>
        <h1 class="mt-2 text-2xl font-bold">{{ data.title }}</h1>
        <p class="mt-3 leading-6 text-muted">{{ data.description }}</p>
        <a routerLink="/dashboard" class="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-primary px-5 font-bold text-on-primary shadow-soft">Zur Übersicht</a>
      </section>
    </main>
  `,
})
export class PlaceholderPage {
  private readonly route = inject(ActivatedRoute);
  protected readonly data = this.route.snapshot.data as PlaceholderData;
}
