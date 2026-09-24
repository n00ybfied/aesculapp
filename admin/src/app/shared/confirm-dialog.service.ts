import { Injectable, signal } from '@angular/core';

export interface ConfirmDialogRequest {
  readonly title: string;
  readonly message: string;
  readonly confirmLabel: string;
  readonly destructive: boolean;
}

@Injectable({ providedIn: 'root' })
export class ConfirmDialogService {
  readonly current = signal<ConfirmDialogRequest | null>(null);
  private resolvePending: ((confirmed: boolean) => void) | null = null;

  confirm(message: string, options: Partial<Omit<ConfirmDialogRequest, 'message'>> = {}): Promise<boolean> {
    if (this.resolvePending !== null) return Promise.resolve(false);
    this.current.set({
      title: options.title ?? 'Bitte bestätigen',
      message,
      confirmLabel: options.confirmLabel ?? 'Bestätigen',
      destructive: options.destructive ?? false,
    });
    return new Promise<boolean>((resolve) => { this.resolvePending = resolve; });
  }

  answer(confirmed: boolean): void {
    const resolve = this.resolvePending;
    this.resolvePending = null;
    this.current.set(null);
    resolve?.(confirmed);
  }
}
