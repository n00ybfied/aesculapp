import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-password-reset-request-page',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './password-reset-request.page.html',
})
export class PasswordResetRequestPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly isSubmitting = signal(false);
  protected readonly isSent = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly resetRequestForm = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting()) {
      return;
    }

    this.submissionError.set(null);
    if (this.resetRequestForm.invalid) {
      this.resetRequestForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const sent = await this.authService.requestPasswordReset(this.resetRequestForm.getRawValue().email);
      if (sent) {
        this.isSent.set(true);
        return;
      }

      this.submissionError.set(authMessages.resetRequestFailed());
    } finally {
      this.isSubmitting.set(false);
    }
  }
}
