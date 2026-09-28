import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';
import { PasswordVisibilityToggleComponent } from '../../../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-registration-page',
  imports: [ReactiveFormsModule, RouterLink, PasswordVisibilityToggleComponent],
  templateUrl: './registration.page.html',
})
export class RegistrationPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly referralCode = this.route.snapshot.queryParamMap.get('ref');

  protected readonly isSubmitting = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly registrationForm = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(10)]],
    passwordConfirmation: ['', [Validators.required]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting()) {
      return;
    }

    this.submissionError.set(null);
    if (this.registrationForm.invalid || this.passwordsDoNotMatch()) {
      this.registrationForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const { passwordConfirmation: _passwordConfirmation, ...details } = this.registrationForm.getRawValue();
      const result = await this.authService.register(this.referralCode ? { ...details, referralCode: this.referralCode } : details);
      if (result === 'verification-required') {
        await this.router.navigate(['/e-mail-bestaetigen'], { queryParams: { email: details.email } });
        return;
      }

      this.submissionError.set(result === 'conflict' ? authMessages.registrationConflict() : result === 'invalid-referral' ? 'Dieser Einladungslink ist ungültig. Bitte bitten Sie die einladende Person um einen neuen Link.' : authMessages.registrationInvalid());
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected passwordsDoNotMatch(): boolean {
    const { password, passwordConfirmation } = this.registrationForm.getRawValue();
    return password !== passwordConfirmation;
  }
}
