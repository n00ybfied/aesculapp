import { TestBed } from '@angular/core/testing';
import { PasswordVisibilityToggleComponent } from './password-visibility-toggle.component';

describe('PasswordVisibilityToggleComponent', () => {
  it('reveals and hides the linked password without changing its value', () => {
    const fixture = TestBed.createComponent(PasswordVisibilityToggleComponent);
    const field = document.createElement('input');
    field.id = 'password-under-test';
    field.type = 'password';
    field.value = 'secret-value';
    fixture.componentRef.setInput('field', field);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    expect(button.type).toBe('button');
    expect(button.getAttribute('aria-label')).toBe('Passwort anzeigen');
    button.click();
    fixture.detectChanges();
    expect(field.type).toBe('text');
    expect(field.value).toBe('secret-value');
    expect(button.getAttribute('aria-label')).toBe('Passwort verbergen');
    button.click();
    fixture.detectChanges();
    expect(field.type).toBe('password');
  });
});
