import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AdminUserService, type AdminUsersOverview, type StaffRole } from '../../core/users/admin-user.service';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { PasswordVisibilityToggleComponent } from '../../shared/password-visibility-toggle.component';

@Component({
  selector: 'app-admin-users',
  imports: [DatePipe, ReactiveFormsModule, PasswordVisibilityToggleComponent],
  templateUrl: './admin-users.component.html',
  styleUrl: './admin-users.component.css',
})
export class AdminUsersComponent {
  private readonly users = inject(AdminUserService);
  private readonly formBuilder = inject(FormBuilder);
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);

  protected readonly areas = [
    ['dashboard', 'Übersicht'], ['slider', 'Dashboard-Slider'], ['statistics', 'Statistik'],
    ['chat', 'Anfragen'], ['news', 'Inhalte'], ['media', 'Medien'],
    ['rewards', 'Prämien'], ['coupons', 'Gutscheine'], ['appointments', 'Termine'],
    ['redemptions', 'Einlösungen'], ['customers', 'Kunden'], ['users', 'Benutzer'],
    ['settings', 'Einstellungen'],
  ] as const;
  private readonly defaultPermissions = ['dashboard', 'slider', 'chat', 'news', 'media', 'rewards', 'coupons', 'appointments', 'redemptions', 'customers'];
  protected readonly selectedPermissions = signal<string[]>(this.defaultPermissions.filter((area) => this.auth.canAccess(area)));
  protected readonly creationMode = signal<'invite' | 'direct'>('invite');
  protected readonly editingUserId = signal<number | null>(null);
  protected readonly editingPermissions = signal<string[]>([]);
  protected readonly editingPasswordUserId = signal<number | null>(null);
  protected readonly changingPassword = signal(false);
  protected readonly isAdmin = this.auth.isTenantAdmin;

  protected readonly overview = signal<AdminUsersOverview | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly isSubmitting = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly success = signal<string | null>(null);
  protected readonly inviteForm = this.formBuilder.nonNullable.group({
    displayName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(160)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    role: this.formBuilder.nonNullable.control<StaffRole>('staff'),
    password: this.formBuilder.nonNullable.control(''),
  });
  protected readonly passwordForm = this.formBuilder.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(4096)]],
    confirmation: ['', [Validators.required]],
  });

  constructor() {
    void this.load();
  }

  protected setCreationMode(mode: 'invite' | 'direct'): void {
    this.creationMode.set(mode);
    const email = this.inviteForm.controls.email;
    email.setValidators([Validators.required, Validators.email, Validators.maxLength(mode === 'direct' ? 100 : 180)]);
    email.updateValueAndValidity();
    const password = this.inviteForm.controls.password;
    password.setValidators(mode === 'direct' ? [Validators.required, Validators.minLength(10)] : []);
    password.reset('');
    password.updateValueAndValidity();
    this.error.set(null);
    this.success.set(null);
  }

  protected async submit(): Promise<void> {
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
      if (this.creationMode() === 'invite') {
        await this.users.invite(value.displayName.trim(), value.email.trim(), value.role, this.selectedPermissions());
        this.success.set('Einladung wurde per E-Mail versendet.');
      } else {
        await this.users.create(value.displayName.trim(), value.email.trim(), value.password, value.role, this.selectedPermissions());
        this.success.set('Mitarbeiterkonto wurde angelegt. Das Anfangspasswort bitte sicher übermitteln.');
      }
      this.inviteForm.reset({ displayName: '', email: '', role: 'staff', password: '' });
      this.selectedPermissions.set(this.defaultPermissions.filter((area) => this.auth.canAccess(area)));
      await this.load();
    } catch (failure: unknown) {
      const serverMessage = failure instanceof HttpErrorResponse && typeof failure.error?.message === 'string' ? failure.error.message : null;
      this.error.set(serverMessage ?? (this.creationMode() === 'invite'
        ? 'Die Einladung konnte nicht versendet werden. Bitte prüfen Sie die Angaben und die SMTP-Einstellungen.'
        : 'Das Mitarbeiterkonto konnte nicht angelegt werden. Bitte prüfen Sie die Angaben.'));
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected roleLabel(roles: readonly string[]): string {
    return roles.includes('ROLE_TENANT_ADMIN') ? 'Administrator' : 'Mitarbeiter';
  }

  protected togglePermission(area: string, checked: boolean, editing = false): void {
    const state = editing ? this.editingPermissions : this.selectedPermissions;
    state.update((current) => checked ? [...current, area] : current.filter((item) => item !== area));
  }

  protected canGrant(area: string): boolean { return this.auth.canAccess(area); }

  protected editUser(id: number, permissions: readonly string[] | null): void {
    this.editingPasswordUserId.set(null);
    this.editingUserId.set(id);
    this.editingPermissions.set([...(permissions ?? this.areas.map(([area]) => area))]);
  }

  protected editPassword(id: number): void {
    this.editingUserId.set(null);
    this.editingPasswordUserId.set(id);
    this.passwordForm.reset({ password: '', confirmation: '' });
    this.error.set(null);
    this.success.set(null);
  }

  protected closePasswordEditor(): void {
    this.editingPasswordUserId.set(null);
    this.passwordForm.reset({ password: '', confirmation: '' });
  }

  protected async savePassword(): Promise<void> {
    const id = this.editingPasswordUserId();
    if (id === null || this.changingPassword()) return;
    this.passwordForm.markAllAsTouched();
    if (this.passwordForm.invalid || this.passwordForm.controls.password.value !== this.passwordForm.controls.confirmation.value) return;

    this.changingPassword.set(true);
    this.error.set(null);
    try {
      await this.users.changePassword(id, this.passwordForm.controls.password.value);
      this.closePasswordEditor();
      if (id === this.auth.currentUserId()) {
        this.auth.logout();
        await this.router.navigateByUrl('/login');
      } else {
        this.success.set('Passwort geändert. Der Benutzer muss sich nach Ablauf seiner aktuellen Sitzung erneut anmelden.');
      }
    } catch (failure: unknown) {
      const serverMessage = failure instanceof HttpErrorResponse && typeof failure.error?.message === 'string' ? failure.error.message : null;
      this.error.set(serverMessage ?? 'Das Passwort konnte nicht geändert werden.');
    } finally {
      this.changingPassword.set(false);
    }
  }

  protected async savePermissions(): Promise<void> {
    const id = this.editingUserId();
    if (id === null) return;
    try {
      await this.users.updatePermissions(id, this.editingPermissions());
      this.editingUserId.set(null);
      await this.load();
      this.success.set('Berechtigungen gespeichert.');
    } catch {
      this.error.set('Berechtigungen konnten nicht gespeichert werden.');
    }
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
