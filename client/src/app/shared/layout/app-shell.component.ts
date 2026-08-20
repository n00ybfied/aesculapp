import { NgOptimizedImage } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { AuthService } from '../../core/auth/auth.service';
import { ThemeService } from '../../core/theme/theme.service';
import { SnackbarComponent } from '../feedback/snackbar.component';

@Component({
  selector: 'app-shell',
  imports: [NgIcon, NgOptimizedImage, RouterLink, RouterLinkActive, RouterOutlet, SnackbarComponent],
  templateUrl: './app-shell.component.html',
})
export class AppShellComponent {
  private readonly navigationTransitionMs = 220;
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly navigationOpen = signal(false);
  protected readonly navigationVisible = signal(false);
  private navigationExitTimer: ReturnType<typeof setTimeout> | undefined;
  private navigationEnterTimer: ReturnType<typeof setTimeout> | undefined;

  protected openNavigation(): void {
    this.clearNavigationTimers();
    this.navigationVisible.set(true);
    this.navigationOpen.set(false);
    this.navigationEnterTimer = setTimeout(() => this.navigationOpen.set(true));
  }

  protected closeNavigation(): void {
    this.navigationOpen.set(false);
    this.clearTimer(this.navigationEnterTimer);
    this.navigationEnterTimer = undefined;
    this.clearTimer(this.navigationExitTimer);
    this.navigationExitTimer = setTimeout(() => this.navigationVisible.set(false), this.navigationTransitionMs);
  }

  protected logout(): void {
    this.authService.logout();
    this.closeNavigation();
    void this.router.navigate(['/login']);
  }

  private clearNavigationTimers(): void {
    this.clearTimer(this.navigationEnterTimer);
    this.clearTimer(this.navigationExitTimer);
    this.navigationEnterTimer = undefined;
    this.navigationExitTimer = undefined;
  }

  private clearTimer(timer: ReturnType<typeof setTimeout> | undefined): void {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  }
}
