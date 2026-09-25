import { Injectable, signal } from '@angular/core';

export interface AchievementCelebration {
  readonly title: string;
  readonly points: number;
}

@Injectable({ providedIn: 'root' })
export class AchievementCelebrationService {
  private readonly active = signal<AchievementCelebration | null>(null);
  readonly current = this.active.asReadonly();

  celebrate(achievement: AchievementCelebration): void {
    this.active.set(achievement);
  }

  dismiss(): void {
    this.active.set(null);
  }
}
