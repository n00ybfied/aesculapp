import { Component, DestroyRef, ElementRef, afterNextRender, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AchievementCelebrationComponent } from './shared/feedback/achievement-celebration.component';
import { observeErrorMessages } from './shared/feedback/error-scroll';
import { NativeLinkService } from './core/deep-links/native-link.service';
import { AuthService } from './core/auth/auth.service';
import { MedicationReminderService } from './core/medications/medication-reminder.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AchievementCelebrationComponent],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly destroyRef = inject(DestroyRef);
  private readonly nativeLinks = inject(NativeLinkService);
  private readonly auth = inject(AuthService);
  private readonly medicationReminders = inject(MedicationReminderService);

  constructor() {
    afterNextRender(() => this.destroyRef.onDestroy(observeErrorMessages(this.host.nativeElement)));
    void this.nativeLinks.initialize();
    void this.medicationReminders.initialize();
    effect(() => { void this.medicationReminders.setUser(this.auth.currentUser()?.id ?? null); });
  }
}
