import { Component, computed, inject, signal } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';

@Component({
  selector: 'app-snackbar',
  imports: [NgIcon],
  templateUrl: './snackbar.component.html',
})
export class SnackbarComponent {
  private readonly statusMessages = inject(StatusMessageService);
  private readonly dismissThreshold = 96;
  private pointerStartX: number | undefined;

  protected readonly message = this.statusMessages.current;
  protected readonly isVisible = this.statusMessages.isVisible;
  protected readonly dragOffsetX = signal(0);
  protected readonly transform = computed(() => {
    if (this.isVisible()) {
      return `translateX(${this.dragOffsetX()}px)`;
    }

    switch (this.statusMessages.exitDirection()) {
      case 'left':
        return 'translateX(-120%)';
      case 'right':
        return 'translateX(120%)';
      default:
        return 'translateY(1rem)';
    }
  });

  protected startDrag(event: PointerEvent): void {
    this.pointerStartX = event.clientX;
    (event.currentTarget as HTMLElement | null)?.setPointerCapture(event.pointerId);
  }

  protected drag(event: PointerEvent): void {
    if (this.pointerStartX === undefined) {
      return;
    }

    this.dragOffsetX.set(event.clientX - this.pointerStartX);
  }

  protected endDrag(): void {
    const offset = this.dragOffsetX();
    if (Math.abs(offset) >= this.dismissThreshold) {
      this.statusMessages.dismiss(undefined, offset < 0 ? 'left' : 'right');
    }

    this.pointerStartX = undefined;
    this.dragOffsetX.set(0);
  }

  protected dismiss(): void {
    this.statusMessages.dismiss();
  }
}
