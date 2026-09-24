import { Component, ElementRef, effect, inject, viewChild } from '@angular/core';
import { ConfirmDialogService } from './confirm-dialog.service';

@Component({
  selector: 'app-confirm-dialog',
  template: `
    @if (dialogs.current(); as dialog) {
      <div class="backdrop" (click)="dialogs.answer(false)">
        <section class="modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message" (click)="$event.stopPropagation()" (keydown.escape)="dialogs.answer(false)" (keydown.tab)="keepFocus($event)">
          <h2 id="confirm-title">{{ dialog.title }}</h2>
          <p id="confirm-message">{{ dialog.message }}</p>
          <div class="actions">
            <button #cancelButton type="button" class="cancel" (click)="dialogs.answer(false)">Abbrechen</button>
            <button #confirmButton type="button" class="confirm" [class.destructive]="dialog.destructive" (click)="dialogs.answer(true)">{{ dialog.confirmLabel }}</button>
          </div>
        </section>
      </div>
    }
  `,
  styles: [`
    .backdrop { position: fixed; inset: 0; z-index: 200; display: grid; place-items: center; padding: 1rem; background: rgb(12 28 43 / .62); }
    .modal { width: min(100%, 28rem); border: 1px solid var(--color-border); border-radius: 1.5rem; background: var(--color-surface); padding: 1.5rem; color: var(--color-foreground); box-shadow: 0 1.5rem 4rem rgb(0 0 0 / .22); }
    h2 { margin: 0; font-size: 1.25rem; font-weight: 700; }
    p { margin: .75rem 0 1.5rem; line-height: 1.5; }
    .actions { display: flex; justify-content: flex-end; gap: .75rem; }
    button { min-height: 3rem; border-radius: .75rem; padding: .65rem 1rem; font: inherit; font-weight: 700; cursor: pointer; }
    .cancel { border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-foreground); }
    .confirm { border: 0; background: var(--color-primary); color: var(--color-on-primary); }
    .confirm.destructive { background: var(--color-danger); color: white; }
    button:focus-visible { outline: 3px solid var(--color-primary); outline-offset: 2px; }
  `],
})
export class ConfirmDialogComponent {
  protected readonly dialogs = inject(ConfirmDialogService);
  private readonly cancelButton = viewChild<ElementRef<HTMLButtonElement>>('cancelButton');
  private readonly confirmButton = viewChild<ElementRef<HTMLButtonElement>>('confirmButton');
  private previousFocus: HTMLElement | null = null;

  constructor() {
    effect(() => {
      if (this.dialogs.current()) {
        this.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        queueMicrotask(() => this.cancelButton()?.nativeElement.focus());
      } else if (this.previousFocus) {
        const previous = this.previousFocus;
        this.previousFocus = null;
        queueMicrotask(() => previous.focus());
      }
    });
  }

  protected keepFocus(event: Event): void {
    if (!(event instanceof KeyboardEvent)) return;
    const cancel = this.cancelButton()?.nativeElement;
    const confirm = this.confirmButton()?.nativeElement;
    if (!cancel || !confirm) return;
    if (event.shiftKey && document.activeElement === cancel) { event.preventDefault(); confirm.focus(); }
    else if (!event.shiftKey && document.activeElement === confirm) { event.preventDefault(); cancel.focus(); }
  }
}
