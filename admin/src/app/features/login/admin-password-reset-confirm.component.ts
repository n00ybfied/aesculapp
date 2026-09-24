import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { TenantBrandingService } from '../../core/settings/tenant-branding.service';
import { PasswordVisibilityToggleComponent } from '../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-admin-password-reset-confirm',
  imports: [ReactiveFormsModule, RouterLink, PasswordVisibilityToggleComponent],
  templateUrl: './admin-password-reset-confirm.component.html',
  styleUrl: './admin-login.component.css',
})
export class AdminPasswordResetConfirmComponent {
  private readonly auth = inject(AdminAuthService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly token = inject(ActivatedRoute).snapshot.queryParamMap.get('token');
  protected readonly branding = inject(TenantBrandingService).branding;
  protected readonly hasToken = this.token !== null && /^[a-f0-9]{64}$/i.test(this.token);
  protected readonly submitting = signal(false);
  protected readonly completed = signal(false);
  protected readonly error = signal('');
  protected readonly form = this.formBuilder.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10)]],
    confirmation: ['', Validators.required],
  });

  protected passwordsDiffer(): boolean {
    const { password, confirmation } = this.form.getRawValue();
    return password !== confirmation;
  }

  protected async submit(): Promise<void> {
    if (this.submitting() || !this.hasToken) return;
    if (this.form.invalid || this.passwordsDiffer()) {
      this.form.markAllAsTouched();
      return;
    }

    this.error.set('');
    this.submitting.set(true);
    try {
      await firstValueFrom(this.auth.resetPassword(this.token!, this.form.getRawValue().password));
      this.completed.set(true);
    } catch {
      this.error.set('Dieser Link ist ungültig oder abgelaufen. Fordern Sie bitte einen neuen an.');
    } finally {
      this.submitting.set(false);
    }
  }
}
