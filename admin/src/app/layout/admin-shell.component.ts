import { Component, OnDestroy, inject, signal } from '@angular/core';
import { ChatService } from '../core/chat/chat.service';
import { OpenChatBadgeComponent } from '../shared/open-chat-badge.component';
import { AdminAppointmentService } from '../core/appointments/admin-appointment.service';
import { AppointmentAlert, StaffAppointmentService } from '../core/appointments/staff-appointment.service';
import { UnseenAppointmentsBadgeComponent } from '../shared/unseen-appointments-badge.component';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AdminAuthService } from '../core/auth/admin-auth.service';
import { TenantBrandingService } from '../core/settings/tenant-branding.service';
import { ConfirmDialogComponent } from '../shared/confirm-dialog.component';

@Component({
  selector: 'app-admin-shell',
  imports: [RouterLink, RouterLinkActive, RouterOutlet, OpenChatBadgeComponent, UnseenAppointmentsBadgeComponent, ConfirmDialogComponent],
  templateUrl: './admin-shell.component.html',
  styleUrl: './admin-shell.component.css',
})
export class AdminShellComponent implements OnDestroy {
  private readonly chats=inject(ChatService);
  private readonly appointments = inject(AdminAppointmentService);
  private readonly staffAppointments = inject(StaffAppointmentService);
  private readonly countTimer:ReturnType<typeof setInterval>;
  private readonly refreshCount=()=>{
    if (document.hidden) return;
    if (this.auth.canAccess('chat')) void this.chats.refreshOpenCount();
    if (this.auth.canAccess('appointments')) void this.appointments.refreshUnseenCount();
    void this.staffAppointments.refreshAlert();
  };
  private readonly auth = inject(AdminAuthService);
  private readonly router = inject(Router);
  private readonly brandingService = inject(TenantBrandingService);

  protected readonly displayName = this.auth.displayName;
  protected readonly canAccess = this.auth.canAccess;
  protected readonly branding = this.brandingService.branding;
  protected readonly appointmentAlert = this.staffAppointments.alert;
  protected readonly isTenantAdmin = this.auth.isTenantAdmin;
  protected readonly navigationOpen = signal(false);

  constructor() {
    void this.brandingService.getPublic();
    this.refreshCount();
    this.countTimer=setInterval(this.refreshCount,5000);
    document.addEventListener('visibilitychange',this.refreshCount);
  }

  ngOnDestroy():void{clearInterval(this.countTimer);document.removeEventListener('visibilitychange',this.refreshCount);this.chats.openCount.set(null);this.appointments.unseenCount.set(null);this.staffAppointments.clearAlert();}

  protected alertText(alert: AppointmentAlert): string {
    const appointments = alert.count === 1 ? 'Termin' : 'Termine';
    if (alert.mode === 'confirmation') {
      return this.isTenantAdmin()
        ? `${alert.count} ${appointments} ${alert.count === 1 ? 'wartet' : 'warten'} auf Bestätigung.`
        : `Sie haben ${alert.count} ${appointments} zur Bestätigung offen.`;
    }
    if (this.isTenantAdmin()) return `${alert.count} ${alert.count === 1 ? 'neuer Termin' : 'neue Termine'}.`;
    return `Sie haben ${alert.count} ${alert.count === 1 ? 'neuen Termin' : 'neue Termine'}.`;
  }

  protected logout(): void {
    this.auth.logout();
    void this.router.navigateByUrl('/login');
  }

  protected toggleNavigation(): void { this.navigationOpen.update((open) => !open); }
  protected closeNavigation(): void { this.navigationOpen.set(false); }
}
