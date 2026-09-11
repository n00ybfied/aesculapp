import { Component, OnDestroy, inject, signal } from '@angular/core';
import { ChatService } from '../core/chat/chat.service';
import { OpenChatBadgeComponent } from '../shared/open-chat-badge.component';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminAuthService } from '../core/auth/admin-auth.service';
import { TenantBrandingService } from '../core/settings/tenant-branding.service';

@Component({
  selector: 'app-admin-shell',
  imports: [RouterLink, RouterLinkActive, RouterOutlet, OpenChatBadgeComponent],
  templateUrl: './admin-shell.component.html',
  styleUrl: './admin-shell.component.css',
})
export class AdminShellComponent implements OnDestroy {
  private readonly chats=inject(ChatService);
  private readonly countTimer:ReturnType<typeof setInterval>;
  private readonly refreshCount=()=>{if(!document.hidden)void this.chats.refreshOpenCount();};
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);
  private readonly brandingService = inject(TenantBrandingService);

  protected readonly displayName = this.auth.displayName;
  protected readonly branding = this.brandingService.branding;
  protected readonly navigationOpen = signal(false);

  constructor() {
    void this.brandingService.get();
    this.refreshCount();
    this.countTimer=setInterval(this.refreshCount,5000);
    document.addEventListener('visibilitychange',this.refreshCount);
  }

  ngOnDestroy():void{clearInterval(this.countTimer);document.removeEventListener('visibilitychange',this.refreshCount);this.chats.openCount.set(null);}

  protected logout(): void {
    this.auth.logout();
    void this.router.navigateByUrl('/login');
  }

  protected toggleNavigation(): void { this.navigationOpen.update((open) => !open); }
  protected closeNavigation(): void { this.navigationOpen.set(false); }
}
