import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { type Achievement } from '../../core/achievements/achievement.service';

@Component({
  selector: 'app-dashboard-achievement-strip',
  imports: [NgIcon, RouterLink],
  template: `
    @if (nextAchievement(); as achievement) {
      <section class="mt-3" aria-label="Nächste Trophäe">
        <a [routerLink]="achievement.actionPath ?? '/trophaeen'" class="flex min-h-16 items-center gap-3 rounded-2xl border border-border bg-surface px-4 py-3 shadow-card transition hover:bg-accent focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary">
          <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-accent text-primary" aria-hidden="true"><ng-icon name="lucideTrophy" size="1.3rem" /></span>
          <span class="min-w-0 flex-1">
            <span class="block text-xs font-bold uppercase tracking-wide text-primary">Nächste Aufgabe</span>
            <span class="block truncate text-sm font-bold text-foreground">{{ achievement.title }}</span>
            <span class="block text-xs text-muted">{{ achievement.progress }} von {{ achievement.target }} geschafft · +{{ achievement.points }} Punkte</span>
          </span>
          <ng-icon name="lucideChevronRight" size="1.1rem" class="shrink-0 text-primary" aria-hidden="true" />
        </a>
        @if (achievements().length > 1) {
          <a routerLink="/trophaeen" class="mt-2 inline-flex min-h-11 items-center text-sm font-bold text-primary underline underline-offset-4 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary">Alle Trophäen</a>
        }
      </section>
    } @else if (achievements().length > 1) {
      <a routerLink="/trophaeen" class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-bold text-primary underline underline-offset-4 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-primary"><ng-icon name="lucideTrophy" size="1.1rem" aria-hidden="true" /> Alle Trophäen</a>
    }
  `,
})
export class DashboardAchievementStripComponent {
  readonly achievements = input.required<readonly Achievement[]>();
  protected readonly nextAchievement = computed(() => this.achievements().find((achievement) => !achievement.completed) ?? null);
}
