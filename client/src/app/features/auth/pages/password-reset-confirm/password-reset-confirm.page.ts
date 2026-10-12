import { Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { authMessages } from '../../../../core/i18n/auth-messages';
import { ThemeService } from '../../../../core/theme/theme.service';
import { PasswordVisibilityToggleComponent } from '../../../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-password-reset-confirm-page',
  imports: [ReactiveFormsModule, RouterLink, PasswordVisibilityToggleComponent],
  templateUrl: './password-reset-confirm.page.html',
})
export class PasswordResetConfirmPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly token = signal<string | null>(null);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly isSubmitting = signal(false);
  protected readonly isCompleted = signal(false);
  protected readonly submissionError = signal<string | null>(null);
  protected readonly hasToken = computed(() => this.token() !== null && /^[a-f0-9]{64}$/i.test(this.token()!));
  protected readonly resetForm = this.formBuilder.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10)]],
    passwordConfirmation: ['', [Validators.required]],
  });

  constructor() {
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((params) => {
      this.token.set(params.get('token'));
      this.isCompleted.set(false);
      this.submissionError.set(null);
      this.resetForm.reset();
    });
  }

  protected async submit(): Promise<void> {
    if (this.isSubmitting() || !this.hasToken()) {
      return;
    }

    this.submissionError.set(null);
    if (this.resetForm.invalid || this.passwordsDoNotMatch()) {
      this.resetForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const result = await this.authService.resetPassword(this.token()!, this.resetForm.getRawValue().password);
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
