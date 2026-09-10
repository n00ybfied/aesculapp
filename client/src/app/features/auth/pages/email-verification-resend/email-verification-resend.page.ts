import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-email-verification-resend-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './email-verification-resend.page.html',
})
export class EmailVerificationResendPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly isSubmitting = signal(false);
  protected readonly isSent = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly form = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting()) {
      return;
    }

    this.submissionError.set(null);
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      if (await this.authService.resendEmailVerification(this.form.getRawValue().email)) {
        this.isSent.set(true);
        return;
      }

      this.submissionError.set(authMessages.verificationResendFailed());
    } finally {
      this.isSubmitting.set(false);
    }
  }
}
