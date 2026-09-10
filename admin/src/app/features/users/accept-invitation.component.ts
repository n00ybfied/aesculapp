import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminUserService } from '../../core/users/admin-user.service';

@Component({
  selector: 'app-accept-invitation',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './accept-invitation.component.html',
  styleUrl: './accept-invitation.component.css',
})
export class AcceptInvitationComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly users = inject(AdminUserService);
  private readonly formBuilder = inject(FormBuilder);
  protected readonly token = this.route.snapshot.queryParamMap.get('token');

  protected readonly isSubmitting = signal(false);
  protected readonly isAccepted = signal(false);
  protected readonly error = signal<string | null>(this.token === null ? 'Diese Einladung ist ungültig oder unvollständig.' : null);
  protected readonly form = this.formBuilder.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10)]],
    passwordConfirmation: ['', [Validators.required]],
  });

  protected async submit(): Promise<void> {
    if (this.isSubmitting() || this.token === null) {
      return;
    }
    this.error.set(null);
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { password, passwordConfirmation } = this.form.getRawValue();
    if (password !== passwordConfirmation) {
      this.error.set('Die beiden Passwörter stimmen nicht überein.');
      return;
    }

    this.isSubmitting.set(true);
    try {
      await this.users.acceptInvitation(this.token, password);
      this.isAccepted.set(true);
    } catch {
      this.error.set('Die Einladung ist ungültig, abgelaufen oder wurde bereits angenommen.');
    } finally {
      this.isSubmitting.set(false);
    }
  }

  protected goToLogin(): void {
    void this.router.navigateByUrl('/login');
  }
}
