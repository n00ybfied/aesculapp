import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { TenantBrandingService } from '../../core/settings/tenant-branding.service';

@Component({
  selector: 'app-admin-password-reset-request',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './admin-password-reset-request.component.html',
  styleUrl: './admin-login.component.css',
})
export class AdminPasswordResetRequestComponent {
  private readonly auth = inject(AdminAuthService);
  private readonly formBuilder = inject(FormBuilder);
  protected readonly branding = inject(TenantBrandingService).branding;
  protected readonly submitting = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal('');
  protected readonly form = this.formBuilder.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  protected async submit(): Promise<void> {
    if (this.submitting()) return;
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.error.set('');
    this.submitting.set(true);
    try {
      await firstValueFrom(this.auth.requestPasswordReset(this.form.getRawValue().email));
      this.sent.set(true);
    } catch {
      this.error.set('Die Anfrage konnte nicht gesendet werden. Bitte versuchen Sie es später erneut.');
    } finally {
      this.submitting.set(false);
    }
  }
}
