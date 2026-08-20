import { NgOptimizedImage } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { AuthService } from '../../core/auth/auth.service';
import { ThemeService } from '../../core/theme/theme.service';

@Component({
  selector: 'app-shell',
  imports: [NgIcon, NgOptimizedImage, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app-shell.component.html',
})
export class AppShellComponent {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  protected readonly theme = inject(ThemeService).activeTheme;

  protected readonly navigationOpen = signal(false);

  protected closeNavigation(): void {
    this.navigationOpen.set(false);
  }

  protected logout(): void {
    this.authService.logout();
    this.closeNavigation();
    void this.router.navigate(['/login']);
  }
}
