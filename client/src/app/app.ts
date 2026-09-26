import { Component, DestroyRef, ElementRef, afterNextRender, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AchievementCelebrationComponent } from './shared/feedback/achievement-celebration.component';
import { observeErrorMessages } from './shared/feedback/error-scroll';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AchievementCelebrationComponent],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    afterNextRender(() => this.destroyRef.onDestroy(observeErrorMessages(this.host.nativeElement)));
  }
}
