import { NgOptimizedImage } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({
  selector: 'app-login-page',
  imports: [NgOptimizedImage, ReactiveFormsModule, RouterLink],
  templateUrl: './login.page.html',
})
export class LoginPage {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly router = inject(Router);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly loginFailed = signal(false);
  protected readonly passwordVisible = signal(false);
  protected readonly loginForm = this.formBuilder.nonNullable.group({
    username: ['', [Validators.required]],
    password: ['', [Validators.required]],
  });

  protected submit(): void {
    this.loginFailed.set(false);

    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    if (!this.authService.login(this.loginForm.getRawValue())) {
      this.loginFailed.set(true);
      return;
    }

    void this.router.navigate(['/dashboard']);
  }

  protected togglePasswordVisibility(): void {
    this.passwordVisible.update((visible) => !visible);
  }
}
