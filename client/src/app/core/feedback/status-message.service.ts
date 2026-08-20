import { Injectable, signal } from '@angular/core';

export type StatusMessageKind = 'error' | 'success' | 'info';
export type StatusMessageExitDirection = 'bottom' | 'left' | 'right';

export interface StatusMessage {
  readonly id: number;
  readonly kind: StatusMessageKind;
  readonly text: string;
}

export interface StatusMessageOptions {
  readonly durationMs?: number;
  readonly kind?: StatusMessageKind;
}

@Injectable({ providedIn: 'root' })
export class StatusMessageService {
  private readonly defaultDurationMs = 5_000;
  private nextId = 0;
  private dismissalTimer: ReturnType<typeof setTimeout> | undefined;
  private enterTimer: ReturnType<typeof setTimeout> | undefined;
  private removalTimer: ReturnType<typeof setTimeout> | undefined;

  readonly current = signal<StatusMessage | null>(null);
  readonly isVisible = signal(false);
  readonly exitDirection = signal<StatusMessageExitDirection>('bottom');

  error(text: string): void {
    this.show(text, { kind: 'error' });
  }

  show(text: string, options: StatusMessageOptions = {}): void {
    this.clearTimers();

    const message = {
      id: this.nextId += 1,
      kind: options.kind ?? 'info',
      text,
    } as const;

    this.current.set(message);
    this.isVisible.set(false);
    this.exitDirection.set('bottom');
    this.enterTimer = setTimeout(() => this.isVisible.set(true));
    this.dismissalTimer = setTimeout(() => this.dismiss(message.id), options.durationMs ?? this.defaultDurationMs);
  }

  dismiss(id?: number, direction: StatusMessageExitDirection = 'bottom'): void {
    if (id !== undefined && this.current()?.id !== id) {
      return;
    }

    const currentMessage = this.current();
    if (!currentMessage) {
      return;
    }

    this.clearTimer(this.dismissalTimer);
    this.dismissalTimer = undefined;
    this.clearTimer(this.enterTimer);
    this.enterTimer = undefined;
    this.clearTimer(this.removalTimer);
    this.removalTimer = undefined;
    this.exitDirection.set(direction);
    this.isVisible.set(false);
    this.removalTimer = setTimeout(() => {
      if (this.current()?.id === currentMessage.id && !this.isVisible()) {
        this.current.set(null);
      }
    }, 220);
  }

  private clearTimers(): void {
    this.clearTimer(this.dismissalTimer);
    this.clearTimer(this.enterTimer);
    this.clearTimer(this.removalTimer);
    this.dismissalTimer = undefined;
    this.enterTimer = undefined;
    this.removalTimer = undefined;
  }

  private clearTimer(timer: ReturnType<typeof setTimeout> | undefined): void {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  }
}
