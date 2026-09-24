import { Component, inject } from '@angular/core';
import { AdminAppointmentService } from '../core/appointments/admin-appointment.service';

@Component({
  selector: 'app-unseen-appointments-badge',
  template: `@if (count(); as count) { <span [attr.aria-label]="count + ' neue, noch nicht angesehene Termine'">{{ count }}</span> }`,
  styles: [`
    :host { display: inline-flex; vertical-align: middle; }
    span { display: inline-flex; align-items: center; justify-content: center; min-width: 1.5rem; height: 1.5rem; padding: 0 .4rem; border-radius: 999px; background: var(--admin-danger); color: white; font-size: .75rem; font-weight: 700; line-height: 1; white-space: nowrap; }
  `],
})
export class UnseenAppointmentsBadgeComponent {
  protected readonly count = inject(AdminAppointmentService).unseenCount;
}
