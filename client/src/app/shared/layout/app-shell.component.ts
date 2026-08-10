import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-shell',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app-shell.component.html',
})
export class AppShellComponent {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);

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
