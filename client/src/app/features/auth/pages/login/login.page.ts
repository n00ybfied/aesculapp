import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { statusMessages } from '../../../../core/i18n/status-messages';
import { ThemeService } from '../../../../core/theme/theme.service';
import { PasswordVisibilityToggleComponent } from '../../../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, PasswordVisibilityToggleComponent],
  templateUrl: './login.page.html',
})
export class LoginPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly loginError = signal<string | null>(null);
  protected readonly isSubmitting = signal(false);
  protected readonly loginForm = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting()) {
      return;
    }

    this.loginError.set(null);

    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const loginResult = await this.authService.login(this.loginForm.getRawValue());
      if (loginResult !== 'success') {
        this.loginError.set(loginResult === 'connection-error'
          ? statusMessages.loginConnectionError
          : loginResult === 'invalid-credentials'
            ? statusMessages.loginInvalidCredentials
            : statusMessages.loginServerError);
        return;
      }

      const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
      await this.router.navigateByUrl(returnUrl !== null && returnUrl.startsWith('/') && !returnUrl.startsWith('//') ? returnUrl : '/dashboard');
    } finally {
      this.isSubmitting.set(false);
    }
  }

}
