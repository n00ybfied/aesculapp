import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

@Component({
  selector: 'app-admin-login',
  imports: [FormsModule],
  templateUrl: './admin-login.component.html',
  styleUrl: './admin-login.component.css',
})
export class AdminLoginComponent {
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);

  protected username = '';
  protected password = '';
  protected readonly isSubmitting = signal(false);
  protected readonly errorMessage = signal('');

  protected submit(): void {
    if (this.isSubmitting() || this.username.trim() === '' || this.password === '') {
      return;
    }

    this.errorMessage.set('');
    this.isSubmitting.set(true);
    this.auth.login(this.username.trim(), this.password)
      .pipe(finalize(() => this.isSubmitting.set(false)))
      .subscribe({
        next: () => void this.router.navigateByUrl('/dashboard'),
        error: (error: HttpErrorResponse) => {
          this.errorMessage.set(error.status === 401
            ? 'Die Zugangsdaten sind nicht für den Adminbereich berechtigt.'
            : 'Der Adminbereich ist derzeit nicht erreichbar.');
        },
      });
  }
}
