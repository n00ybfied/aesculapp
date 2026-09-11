import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { RewardCatalogService } from '../../core/rewards/reward-catalog.service';
import type { Reward } from '../../core/rewards/reward.repository';

@Component({
  selector: 'app-reward-detail-page',
  imports: [RouterLink],
  template: `
    @if (loading()) {
      <main class="grid min-h-[calc(100dvh-8.5rem)] place-items-center bg-background" aria-label="Gutschein wird geladen" i18n-aria-label>
        <span class="size-10 animate-spin rounded-full border-4 border-accent border-t-primary" aria-hidden="true"></span>
      </main>
    } @else {
      <main class="px-5 py-6">
        <a routerLink="/punkte" class="mb-5 inline-flex min-h-11 items-center rounded-lg px-2 font-semibold text-primary focus-visible:outline-3 focus-visible:outline-primary" i18n>← Zurück zu Prämien</a>
        @if (reward(); as item) {
          <article class="overflow-hidden rounded-2xl border border-border bg-surface shadow-card">
            @if (item.imageUrl) {
              <img [src]="item.imageUrl" alt="" class="max-h-72 w-full object-contain" />
            }
            <div class="p-5">
              <p class="text-sm font-semibold text-primary" i18n>{{ item.requiredPoints }} Punkte</p>
              <h1 class="mt-2 text-3xl">{{ item.title }}</h1>
              <p class="mt-3 text-lg text-muted">{{ item.subtitle }}</p>
              <div class="mt-6 break-words leading-7 [&_img]:max-w-full [&_img]:h-auto [&_p]:my-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_a]:text-primary [&_a]:underline [&_h2]:mt-5 [&_h2]:text-2xl [&_h3]:mt-4 [&_h3]:text-xl [&_blockquote]:border-l-4 [&_blockquote]:border-primary [&_blockquote]:pl-4" [innerHTML]="item.description"></div>
            </div>
          </article>
        } @else {
          <p role="alert" class="rounded-2xl bg-surface p-5 text-muted">{{ error() }}</p>
        }
      </main>
    }
  `,
})
export class RewardDetailPage {
  private readonly catalog = inject(RewardCatalogService);
  private readonly id = inject(ActivatedRoute).snapshot.paramMap.get('id');
  protected readonly reward = signal<Reward | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  constructor() { void this.load(); }

  private async load(): Promise<void> {
    try {
      const reward = this.id ? await this.catalog.getVisibleReward(this.id) : null;
      this.reward.set(reward);
      if (!reward) this.error.set($localize`:@@reward.detail.not-found:Dieser Gutschein ist nicht mehr verfügbar.`);
    } catch {
      this.error.set($localize`:@@reward.detail.load-error:Der Gutschein konnte nicht geladen werden. Bitte versuchen Sie es später erneut.`);
    } finally {
      this.loading.set(false);
    }
  }
}
