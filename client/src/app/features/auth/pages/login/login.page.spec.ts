import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { AuthService } from '../../../../core/auth/auth.service';
import { ThemeService } from '../../../../core/theme/theme.service';
import { LoginPage } from './login.page';

describe('LoginPage', () => {
  it('shows a clear connection error when the API is unavailable', async () => {
    TestBed.configureTestingModule({
      imports: [LoginPage],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { login: vi.fn().mockResolvedValue('connection-error') } },
        { provide: ThemeService, useValue: { activeTheme: { logoPath: '', pharmacyName: 'Testapotheke' } } },
      ],
    });

    const fixture = TestBed.createComponent(LoginPage);
    fixture.detectChanges();
    const page = fixture.nativeElement as HTMLElement;
    const email = page.querySelector<HTMLInputElement>('#email')!;
    const password = page.querySelector<HTMLInputElement>('#password')!;
    email.value = 'kunde@example.test';
    email.dispatchEvent(new Event('input'));
    password.value = 'geheim';
    password.dispatchEvent(new Event('input'));
    page.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await fixture.whenStable();
    fixture.detectChanges();
    expect(page.querySelector('[role="alert"]')?.textContent?.trim()).toBe('Verbindungsfehler: Bitte versuchen Sie es später erneut');
  });
});
