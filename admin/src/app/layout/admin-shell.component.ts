import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminAuthService } from '../core/auth/admin-auth.service';
import { TenantBrandingService } from '../core/settings/tenant-branding.service';

@Component({
  selector: 'app-admin-shell',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './admin-shell.component.html',
  styleUrl: './admin-shell.component.css',
})
export class AdminShellComponent {
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);
  private readonly brandingService = inject(TenantBrandingService);

  protected readonly displayName = this.auth.displayName;
  protected readonly branding = this.brandingService.branding;
  protected readonly navigationOpen = signal(false);

  constructor() {
    void this.brandingService.get();
  }

  protected logout(): void {
    this.auth.logout();
    void this.router.navigateByUrl('/login');
  }

  protected toggleNavigation(): void { this.navigationOpen.update((open) => !open); }
  protected closeNavigation(): void { this.navigationOpen.set(false); }
}
