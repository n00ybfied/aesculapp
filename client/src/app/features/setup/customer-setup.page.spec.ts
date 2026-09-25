import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { CustomerProfile, CustomerSetupDetails, ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { AchievementCelebrationService } from '../../core/achievements/achievement-celebration.service';
import { CustomerSetupPage } from './customer-setup.page';

describe('CustomerSetupPage', () => {
  it('moves through all three steps and saves the selected interests once', async () => {
    let submitted: CustomerSetupDetails | null = null;
    const celebrate = vi.fn();
    const profile = {
      id: 1, setupCompleted: false, profileCompletionBonusPoints: 55, salutation: null, firstName: null, lastName: null,
      phone: null, streetAddress: null, postalCode: null, city: null, newsCategoryIds: [],
      newsletterEnabled: false, chatPushEnabled: false, rewardPushEnabled: false,
      newsPushEnabled: false, medicationPushEnabled: false, appointmentPushEnabled: true,
      familyPushEnabled: true,
    } as unknown as CustomerProfile;
    const navigateByUrl = vi.fn(async () => true);
    TestBed.configureTestingModule({
      imports: [CustomerSetupPage],
      providers: [
        { provide: ProfileService, useValue: {
          load: async () => profile,
          loadNewsCategories: async () => [{ id: 7, name: 'Gesundheitstipps' }],
          completeSetup: async (details: CustomerSetupDetails) => { submitted = details; return { ...profile, setupCompleted: true, profileComplete: true, profileCompletionBonusAwarded: true }; },
        } },
        { provide: AuthService, useValue: { currentUser: signal({ id: 1 }), logout: vi.fn() } },
        { provide: Router, useValue: { navigateByUrl } },
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: { get: () => null } } } },
        { provide: ThemeService, useValue: { activeTheme: { logoPath: '/logo.svg', pharmacyName: 'Testapotheke' } } },
        { provide: AchievementCelebrationService, useValue: { celebrate } },
      ],
    });

    const fixture = TestBed.createComponent(CustomerSetupPage);
    fixture.detectChanges();
    await new Promise<void>((resolve) => setTimeout(resolve, 0));
    await fixture.whenStable();
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    const setInput = (selector: string, value: string): void => {
      const input = root.querySelector<HTMLInputElement | HTMLSelectElement>(selector);
      if (!input) throw new Error(`Missing input: ${selector}`);
      input.value = value;
      input.dispatchEvent(new Event(input instanceof HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
    };
    const submit = async (): Promise<void> => {
      root.querySelector('form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      await fixture.whenStable();
      fixture.detectChanges();
    };

    await submit();
    expect(root.textContent).toContain('Schritt 2 von 3');
    Array.from(root.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Zurück')?.click();
    await fixture.whenStable();
    fixture.detectChanges();
    expect(root.textContent).toContain('Schritt 1 von 3');

    setInput('[formControlName="salutation"]', 'frau');
    setInput('[formControlName="firstName"]', 'Erika');
    setInput('[formControlName="lastName"]', 'Beispiel');
    await submit();
    expect(root.textContent).toContain('Schritt 2 von 3');

    Array.from(root.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Zurück')?.click();
    await fixture.whenStable();
    fixture.detectChanges();
    expect(root.textContent).toContain('Schritt 1 von 3');
    expect(root.querySelector<HTMLInputElement>('[formControlName="firstName"]')?.value).toBe('Erika');
    await submit();

    setInput('[formControlName="phone"]', '+43 123 456');
    setInput('[formControlName="birthDate"]', '1990-06-15');
    await submit();
    expect(root.textContent).toContain('Schritt 3 von 3');

    const category = root.querySelector<HTMLInputElement>('input[type="checkbox"]:not([formControlName])');
    expect(category).not.toBeNull();
    category?.click();
    root.querySelector<HTMLInputElement>('[formControlName="newsPushEnabled"]')?.click();
    await submit();

    expect(submitted).toMatchObject({ firstName: 'Erika', lastName: 'Beispiel', salutation: 'frau', phone: '+43 123 456', birthDate: '1990-06-15', newsCategoryIds: [7], newsPushEnabled: true });
    expect(navigateByUrl).toHaveBeenCalledWith('/dashboard');
    expect(celebrate).toHaveBeenCalledWith({ title: 'Profil vervollständigen', points: 55 });
  });

  it('lets a new customer skip the setup without completing personal fields', async () => {
    const navigateByUrl = vi.fn(async () => true);
    const skipSetup = vi.fn(async () => ({ id: 1, setupCompleted: true }));
    TestBed.configureTestingModule({
      imports: [CustomerSetupPage],
      providers: [
        { provide: ProfileService, useValue: {
          load: async () => ({ id: 1, setupCompleted: false, newsCategoryIds: [] }),
          loadNewsCategories: async () => [],
          skipSetup,
        } },
        { provide: AuthService, useValue: { currentUser: signal({ id: 1 }), logout: vi.fn() } },
        { provide: Router, useValue: { navigateByUrl } },
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: { get: () => null } } } },
        { provide: ThemeService, useValue: { activeTheme: { logoPath: '/logo.svg', pharmacyName: 'Testapotheke' } } },
        { provide: StatusMessageService, useValue: { show: vi.fn() } },
        { provide: AchievementCelebrationService, useValue: { celebrate: vi.fn() } },
      ],
    });
    const fixture = TestBed.createComponent(CustomerSetupPage);
    fixture.detectChanges();
    await new Promise<void>((resolve) => setTimeout(resolve, 0));
    await fixture.whenStable();
    fixture.detectChanges();
    const skipButton = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll('button'))
      .find((button) => button.textContent?.includes('Jetzt nicht'));
    skipButton?.click();
    await fixture.whenStable();
    expect(skipSetup).toHaveBeenCalledOnce();
    expect(navigateByUrl).toHaveBeenCalledWith('/dashboard');
  });
});
