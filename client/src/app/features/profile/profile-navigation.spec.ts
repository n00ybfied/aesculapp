import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { CustomerProfile, ProfileService } from '../../core/profile/profile.service';
import { provideLucideIcons } from '../../core/icons/lucide-icons';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ConfirmDialogService } from '../../shared/feedback/confirm-dialog.service';
import { ProfileOverviewPage } from './profile-overview.page';
import { UsernamePage } from './username.page';
import { DeleteAccountPage } from './delete-account.page';

const profile = {
  id: 1,
  username: 'kunde@example.test',
  email: 'kunde@example.test',
  displayName: 'Erika Beispiel',
  firstName: 'Erika',
  lastName: 'Beispiel',
} as CustomerProfile;

describe('Customer account pages', () => {
  it('offers separate links for profile editing, username and deletion', async () => {
    TestBed.configureTestingModule({
      imports: [ProfileOverviewPage],
      providers: [
        provideRouter([]),
        provideLucideIcons(),
        { provide: ProfileService, useValue: { profile: signal(profile), load: async () => profile } },
      ],
    });
    const fixture = TestBed.createComponent(ProfileOverviewPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    const links = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<HTMLAnchorElement>('nav a'));
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/profil/bearbeiten', '/profil/benutzername', '/profil/konto-loeschen',
    ]);
  });

  it('requests email verification before changing the email and username', async () => {
    const requestEmailChange = vi.fn(async () => 'Bestätigungslink gesendet.');
    const currentProfile = signal<CustomerProfile | null>(profile);
    TestBed.configureTestingModule({
      imports: [UsernamePage],
      providers: [
        provideRouter([]),
        { provide: ProfileService, useValue: { profile: currentProfile, load: async () => profile, requestEmailChange } },
      ],
    });
    const fixture = TestBed.createComponent(UsernamePage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    await fixture.whenStable();
    const root = fixture.nativeElement as HTMLElement;
    const email = root.querySelector<HTMLInputElement>('#new-email')!;
    const password = root.querySelector<HTMLInputElement>('#username-password')!;
    email.value = 'erika.neu@example.test';
    email.dispatchEvent(new Event('input', { bubbles: true }));
    password.value = 'mein-passwort';
    password.dispatchEvent(new Event('input', { bubbles: true }));
    fixture.detectChanges();
    await fixture.whenStable();
    expect((fixture.componentInstance as unknown as { email: string; password: string }).email).toBe('erika.neu@example.test');
    expect((fixture.componentInstance as unknown as { username: string; password: string }).password).toBe('mein-passwort');
    root.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();
    expect(requestEmailChange).toHaveBeenCalledWith('erika.neu@example.test', 'mein-passwort');
    expect(root.textContent).toContain('Bis zur Bestätigung bleiben Ihre bisherigen Anmeldedaten gültig.');
    expect(currentProfile()?.email).toBe('kunde@example.test');
  });

  it('deletes an account only after password entry and confirmation', async () => {
    const deleteAccount = vi.fn(async () => undefined);
    const confirm = vi.fn().mockResolvedValueOnce(false).mockResolvedValueOnce(true);
    const logout = vi.fn();
    TestBed.configureTestingModule({
      imports: [DeleteAccountPage],
      providers: [
        provideRouter([]),
        { provide: ProfileService, useValue: { deleteAccount } },
        { provide: AuthService, useValue: { logout } },
        { provide: StatusMessageService, useValue: { show: vi.fn() } },
        { provide: ConfirmDialogService, useValue: { confirm } },
      ],
    });
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const fixture = TestBed.createComponent(DeleteAccountPage);
    fixture.detectChanges();
    await fixture.whenStable();
    const root = fixture.nativeElement as HTMLElement;
    const password = root.querySelector<HTMLInputElement>('#delete-account-password')!;
    password.value = 'mein-passwort';
    password.dispatchEvent(new Event('input', { bubbles: true }));
    fixture.detectChanges();
    await fixture.whenStable();
    expect((fixture.componentInstance as unknown as { password: string }).password).toBe('mein-passwort');
    root.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();
    expect(confirm).toHaveBeenCalledOnce();
    expect(deleteAccount).not.toHaveBeenCalled();
    password.value = 'mein-passwort';
    password.dispatchEvent(new Event('input', { bubbles: true }));
    fixture.detectChanges();
    await fixture.whenStable();
    root.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();
    expect(deleteAccount).toHaveBeenCalledWith('mein-passwort');
    expect(logout).toHaveBeenCalledOnce();
    expect(navigate).toHaveBeenCalledWith('/login');
  });
});
