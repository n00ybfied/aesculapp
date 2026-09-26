import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, inject, signal, viewChild, viewChildren } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { CustomerSetupDetails, ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { AchievementCelebrationService } from '../../core/achievements/achievement-celebration.service';

@Component({
  selector: 'app-customer-setup',
  imports: [ReactiveFormsModule],
  templateUrl: './customer-setup.page.html',
  host: { '(window:resize)': 'updatePanelHeight()' },
})
export class CustomerSetupPage {
  private readonly profiles = inject(ProfileService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly formBuilder = inject(FormBuilder);
  private readonly celebrations = inject(AchievementCelebrationService);
  private achievementCompletedBeforeSetup = false;
  private readonly headings = viewChildren<ElementRef<HTMLElement>>('stepHeading');
  private readonly panels = viewChildren<ElementRef<HTMLElement>>('stepPanel');
  private readonly viewport = viewChild<ElementRef<HTMLElement>>('stepViewport');
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly step = signal(0);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly loadFailed = signal(false);
  protected readonly categories = signal<readonly { id: number; name: string }[]>([]);
  protected readonly selectedCategoryIds = signal<readonly number[]>([]);
  protected readonly panelHeight = signal<number | null>(null);
  protected readonly bonusPoints = signal(0);
  protected readonly form = this.formBuilder.nonNullable.group({
    salutation: [''],
    firstName: ['', Validators.maxLength(80)],
    lastName: ['', Validators.maxLength(80)],
    phone: ['', Validators.maxLength(40)],
    streetAddress: ['', Validators.maxLength(160)],
    postalCode: ['', Validators.maxLength(20)],
    city: ['', Validators.maxLength(120)],
    birthDate: [''],
    newsletterEnabled: false,
    chatPushEnabled: false,
    rewardPushEnabled: false,
    newsPushEnabled: false,
    medicationPushEnabled: false,
    appointmentPushEnabled: true,
    familyPushEnabled: true,
  });

  constructor() { void this.load(); }

  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    this.loadFailed.set(false);
    try {
      const [profile, categories] = await Promise.all([this.profiles.load(), this.profiles.loadNewsCategories()]);
      if (profile.id !== this.auth.currentUser()?.id) throw new Error('Wrong profile.');
      if (profile.setupCompleted) { await this.router.navigateByUrl('/dashboard'); return; }
      this.achievementCompletedBeforeSetup = profile.profileComplete || profile.profileCompletionBonusAwarded;
      this.categories.set(categories);
      this.bonusPoints.set(profile.profileCompletionBonusPoints);
      this.selectedCategoryIds.set(profile.newsCategoryIds.filter((id) => categories.some((category) => category.id === id)));
      this.form.patchValue({
        salutation: profile.salutation ?? '',
        firstName: profile.firstName ?? '',
        lastName: profile.lastName ?? '',
        phone: profile.phone ?? '',
        streetAddress: profile.streetAddress ?? '',
        postalCode: profile.postalCode ?? '',
        city: profile.city ?? '',
        birthDate: profile.birthDate ?? '',
        newsletterEnabled: profile.newsletterEnabled,
        chatPushEnabled: profile.chatPushEnabled,
        rewardPushEnabled: profile.rewardPushEnabled,
        newsPushEnabled: profile.newsPushEnabled,
        medicationPushEnabled: profile.medicationPushEnabled,
        appointmentPushEnabled: profile.appointmentPushEnabled,
        familyPushEnabled: profile.familyPushEnabled,
      });
      this.focusStep(0);
    } catch {
      this.loadFailed.set(true);
      this.error.set('Die Einrichtung konnte nicht geladen werden. Bitte versuchen Sie es erneut.');
    } finally {
      this.loading.set(false);
    }
  }

  protected async next(): Promise<void> {
    if (this.saving()) return;
    this.error.set('');
    if (this.step() === 0) {
      for (const key of ['firstName', 'lastName'] as const) this.form.controls[key].markAsTouched();
      if (this.form.controls.firstName.invalid || this.form.controls.lastName.invalid) { setTimeout(() => this.updatePanelHeight()); return; }
      if ((this.form.controls.firstName.value.trim() + ' ' + this.form.controls.lastName.value.trim()).length > 160) {
        this.error.set('Vor- und Nachname sind zusammen zu lang.');
        return;
      }
      this.goTo(1);
      return;
    }
    if (this.step() === 1) {
      for (const key of ['phone', 'streetAddress', 'postalCode', 'city', 'birthDate'] as const) this.form.controls[key].markAsTouched();
      if (this.form.controls.phone.invalid || this.form.controls.streetAddress.invalid || this.form.controls.postalCode.invalid || this.form.controls.city.invalid || this.form.controls.birthDate.invalid) { setTimeout(() => this.updatePanelHeight()); return; }
      const birthDate = this.form.controls.birthDate.value;
      if (birthDate) {
        const youngest = new Date();
        youngest.setFullYear(youngest.getFullYear() - 14);
        const oldest = new Date();
        oldest.setFullYear(oldest.getFullYear() - 120);
        if (birthDate > this.dateOnly(youngest) || birthDate < this.dateOnly(oldest)) {
          this.error.set('Bitte geben Sie ein gültiges Geburtsdatum ein (Alter zwischen 14 und 120 Jahren).');
          return;
        }
      }
      this.goTo(2);
      return;
    }

    const values = this.form.getRawValue();
    const details: CustomerSetupDetails = {
      ...values,
      salutation: (values.salutation || null) as CustomerSetupDetails['salutation'],
      firstName: values.firstName.trim() || null,
      lastName: values.lastName.trim() || null,
      phone: values.phone.trim() || null,
      streetAddress: values.streetAddress.trim() || null,
      postalCode: values.postalCode.trim() || null,
      city: values.city.trim() || null,
      birthDate: values.birthDate || null,
      newsCategoryIds: this.selectedCategoryIds(),
    };
    this.saving.set(true);
    try {
      const profile = await this.profiles.completeSetup(details);
      await this.navigateAfterSetup();
      if (!this.achievementCompletedBeforeSetup && (profile.profileComplete || profile.profileCompletionBonusAwarded)) {
        this.celebrations.celebrate({ title: 'Profil vervollständigen', points: profile.profileCompletionBonusAwarded ? profile.profileCompletionBonusPoints : 0 });
      }
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Die Einrichtung konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.');
    } finally {
      this.saving.set(false);
    }
  }

  protected back(): void { if (this.step() > 0) this.goTo(this.step() - 1); }
  protected async skip(): Promise<void> {
    if (this.saving()) return;
    this.saving.set(true);
    this.error.set('');
    try {
      await this.profiles.skipSetup();
      await this.navigateAfterSetup();
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Bitte versuchen Sie es erneut.');
    } finally {
      this.saving.set(false);
    }
  }
  protected logout(): void { this.auth.logout(); void this.router.navigateByUrl('/login'); }
  protected toggleCategory(id: number, selected: boolean): void {
    this.selectedCategoryIds.update((ids) => selected ? [...ids, id] : ids.filter((item) => item !== id));
  }

  private goTo(nextStep: number): void {
    this.step.set(nextStep);
    this.focusStep(nextStep);
  }

  private focusStep(targetStep: number): void {
    setTimeout(() => {
      if (this.step() !== targetStep) return;
      // Focusing a panel during its slide must not scroll the clipped viewport horizontally.
      const viewport = this.viewport()?.nativeElement;
      if (viewport) viewport.scrollLeft = 0;
      this.headings()[targetStep]?.nativeElement.focus({ preventScroll: true });
      this.updatePanelHeight();
    });
  }

  protected updatePanelHeight(): void {
    const height = this.panels()[this.step()]?.nativeElement.offsetHeight;
    if (height) this.panelHeight.set(height);
  }

  private dateOnly(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  private async navigateAfterSetup(): Promise<void> {
    const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
    await this.router.navigateByUrl(returnUrl?.startsWith('/') && !returnUrl.startsWith('//') && !returnUrl.startsWith('/einrichtung') ? returnUrl : '/dashboard');
  }
}
