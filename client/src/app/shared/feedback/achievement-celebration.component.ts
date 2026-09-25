import { Component, ElementRef, effect, inject, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { AchievementCelebrationService } from '../../core/achievements/achievement-celebration.service';

@Component({
  selector: 'app-achievement-celebration',
  imports: [NgIcon, RouterLink],
  templateUrl: './achievement-celebration.component.html',
  styleUrl: './achievement-celebration.component.css',
})
export class AchievementCelebrationComponent {
  protected readonly celebrations = inject(AchievementCelebrationService);
  private readonly closeButton = viewChild<ElementRef<HTMLButtonElement>>('closeButton');
  private readonly continueButton = viewChild<ElementRef<HTMLButtonElement>>('continueButton');
  private previousFocus: HTMLElement | null = null;

  constructor() {
    effect(() => {
      if (this.celebrations.current()) {
        this.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        queueMicrotask(() => this.closeButton()?.nativeElement.focus());
      } else if (this.previousFocus) {
        const previous = this.previousFocus;
        this.previousFocus = null;
        queueMicrotask(() => { if (previous.isConnected) previous.focus(); });
      }
    });
  }

  protected keepFocus(event: Event): void {
    if (!(event instanceof KeyboardEvent)) return;
    const first = this.closeButton()?.nativeElement;
    const last = this.continueButton()?.nativeElement;
    if (!first || !last) return;
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  protected dismiss(): void { this.celebrations.dismiss(); }
}
