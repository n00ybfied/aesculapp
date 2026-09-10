import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { AdminUserService, type AdminUsersOverview } from '../../core/users/admin-user.service';

@Component({
  selector: 'app-admin-users',
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './admin-users.component.html',
  styleUrl: './admin-users.component.css',
})
export class AdminUsersComponent {
  private readonly users = inject(AdminUserService);
  private readonly formBuilder = inject(FormBuilder);

  protected readonly overview = signal<AdminUsersOverview | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly isSubmitting = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly success = signal<string | null>(null);
  protected readonly inviteForm = this.formBuilder.nonNullable.group({
    displayName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(160)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    role: ['staff' as const],
  });

  constructor() {
    void this.load();
  }

  protected async invite(): Promise<void> {
    if (this.isSubmitting()) {
      return;
    }
    this.error.set(null);
    this.success.set(null);
    if (this.inviteForm.invalid) {
      this.inviteForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    try {
      const value = this.inviteForm.getRawValue();
      await this.users.invite(value.displayName.trim(), value.email.trim(), value.role);
      this.success.set('Einladung wurde per E-Mail versendet.');
      this.inviteForm.reset({ displayName: '', email: '', role: 'staff' });
      await this.load();
    } catch {
      this.error.set('Die Einladung konnte nicht versendet werden. Bitte prüfen Sie die Angaben und die SMTP-Einstellungen.');
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected roleLabel(roles: readonly string[]): string {
    return roles.includes('ROLE_TENANT_ADMIN') ? 'Administrator' : 'Mitarbeiter';
  }

  private async load(): Promise<void> {
    this.isLoading.set(true);
    try {
      this.overview.set(await this.users.getOverview());
      this.error.set(null);
    } catch {
      this.error.set('Benutzerverwaltung konnte nicht geladen werden.');
    } finally {
      this.isLoading.set(false);
    }
  }
}
