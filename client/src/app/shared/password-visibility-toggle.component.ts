import { Component, input, signal } from '@angular/core';

@Component({
  selector: 'app-password-visibility-toggle',
  template: `
    <button type="button" class="password-toggle"
      [attr.aria-label]="visible() ? 'Passwort verbergen' : 'Passwort anzeigen'"
      [attr.aria-controls]="field().id || null"
      [attr.aria-pressed]="visible()"
      (click)="toggle()">
      @if (visible()) {
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8" />
          <path d="M9.9 5.2A10.9 10.9 0 0 1 12 5c4.8 0 8.3 3.3 10 7-1 2.1-2.5 3.9-4.4 5.1M6.5 6.5C4.6 7.8 3.1 9.7 2 12c1.7 3.7 5.2 7 10 7 1 0 2-.1 2.9-.4" />
        </svg>
      } @else {
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      }
    </button>
  `,
  styles: [`
    :host { position: absolute; top: 50%; right: .25rem; transform: translateY(-50%); display: block; }
    button { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; min-height: 2.5rem; margin: 0; padding: 0; border: 0; border-radius: .5rem; background: transparent; color: inherit; cursor: pointer; }
    button:hover { background: color-mix(in srgb, currentColor 8%, transparent); }
    button:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
    svg { width: 1.25rem; height: 1.25rem; }
  `],
})
export class PasswordVisibilityToggleComponent {
  readonly field = input.required<HTMLInputElement>();
  protected readonly visible = signal(false);

  protected toggle(): void {
    const visible = !this.visible();
    this.field().type = visible ? 'text' : 'password';
    this.visible.set(visible);
  }
}
