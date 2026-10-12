import { Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../../../core/auth/auth.service';
import { ThemeService } from '../../../../core/theme/theme.service';

@Component({ selector: 'app-email-verification-page', imports: [RouterLink], templateUrl: './email-verification.page.html' })
export class EmailVerificationPage {
  private readonly route = inject(ActivatedRoute);
  private readonly auth = inject(AuthService);
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly token = signal<string | null>(null);
  protected readonly email = signal<string | null>(null);
  protected readonly status = signal<'idle' | 'checking' | 'success' | 'invalid'>('idle');
  protected readonly isConfirmationLink = computed(() => this.token() !== null);

  constructor() {
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((params) => {
      const token = params.get('token');
      this.token.set(token);
      this.email.set(params.get('email'));
      this.status.set(token ? 'checking' : 'idle');
      if (token) void this.confirm(token);
    });
  }

  private async confirm(token: string): Promise<void> {
    const confirmed = await this.auth.confirmEmailVerification(token);
    if (this.token() === token) this.status.set(confirmed ? 'success' : 'invalid');
  }
}
