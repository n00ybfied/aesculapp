import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-registration-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './registration.page.html',
})
export class RegistrationPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly isSubmitting = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly registrationForm = this.formBuilder.nonNullable.group({
    displayName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(160)]],
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,99}$/)]],
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
      const result = await this.authService.register(details);
      if (result === 'success') {
        await this.router.navigate(['/dashboard']);
        return;
      }

      this.submissionError.set(result === 'conflict' ? authMessages.registrationConflict() : authMessages.registrationInvalid());
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected passwordsDoNotMatch(): boolean {
    const { password, passwordConfirmation } = this.registrationForm.getRawValue();
    return password !== passwordConfirmation;
  }
}
