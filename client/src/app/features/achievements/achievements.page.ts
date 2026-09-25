import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { Achievement, AchievementService } from '../../core/achievements/achievement.service';

@Component({
  selector: 'app-achievements-page',
  imports: [NgIcon, RouterLink],
  templateUrl: './achievements.page.html',
})
export class AchievementsPage implements OnInit {
  private readonly achievementService = inject(AchievementService);
  protected readonly achievements = signal<readonly Achievement[]>([]);
  protected readonly completedCount = computed(() => this.achievements().filter((achievement) => achievement.completed).length);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  ngOnInit(): void {
    void this.load();
  }

  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(false);
    try {
      this.achievements.set(await this.achievementService.list());
    } catch {
      this.error.set(true);
    } finally {
      this.loading.set(false);
    }
  }
}
