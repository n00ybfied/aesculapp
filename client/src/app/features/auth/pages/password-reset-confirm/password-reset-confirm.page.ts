import { NgOptimizedImage } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-password-reset-confirm-page',
  imports: [NgOptimizedImage, ReactiveFormsModule, RouterLink],
  templateUrl: './password-reset-confirm.page.html',
})
export class PasswordResetConfirmPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly token = inject(ActivatedRoute).snapshot.queryParamMap.get('token');
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly isSubmitting = signal(false);
  protected readonly isCompleted = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly hasToken = this.token !== null && this.token.length === 64;
  protected readonly resetForm = this.formBuilder.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10)]],
    passwordConfirmation: ['', [Validators.required]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting() || !this.hasToken) {
      return;
    }

    this.submissionError.set(null);
    if (this.resetForm.invalid || this.passwordsDoNotMatch()) {
      this.resetForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const result = await this.authService.resetPassword(this.token!, this.resetForm.getRawValue().password);
      if (result === 'success') {
        this.isCompleted.set(true);
        return;
      }
      this.submissionError.set(authMessages.resetInvalid());
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected async goToLogin(): Promise<void> {
    await this.router.navigate(['/login']);
  }

  protected passwordsDoNotMatch(): boolean {
    const { password, passwordConfirmation } = this.resetForm.getRawValue();
    return password !== passwordConfirmation;
  }
}
