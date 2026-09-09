import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({ selector: 'app-email-verification-page', imports: [RouterLink], templateUrl: './email-verification.page.html' })
export class EmailVerificationPage {
  private readonly route = inject(ActivatedRoute);
  private readonly auth = inject(AuthService);
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly token = this.route.snapshot.queryParamMap.get('token');
  protected readonly email = this.route.snapshot.queryParamMap.get('email');
  protected readonly status = signal<'idle' | 'checking' | 'success' | 'invalid'>(this.token ? 'checking' : 'idle');
  protected readonly isConfirmationLink = computed(() => this.token !== null);

  constructor() { if (this.token) { void this.confirm(this.token); } }

  private async confirm(token: string): Promise<void> { this.status.set(await this.auth.confirmEmailVerification(token) ? 'success' : 'invalid'); }
}
