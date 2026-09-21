import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AppointmentService, type CustomerAppointment } from '../../core/appointments/appointment.service';

@Component({
  selector: 'app-my-appointments-page',
  imports: [RouterLink],
  templateUrl: './my-appointments.page.html',
})
export class MyAppointmentsPage implements OnInit {
  private readonly appointmentsService = inject(AppointmentService);
  protected readonly appointments = signal<readonly CustomerAppointment[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isCancelling = signal<number | null>(null);
  protected readonly error = signal('');
  protected readonly message = signal('');

  async ngOnInit(): Promise<void> { await this.load(); }

  protected async cancel(appointment: CustomerAppointment): Promise<void> {
    if (!confirm('Möchten Sie diesen Termin wirklich absagen?')) return;
    this.isCancelling.set(appointment.id);
    this.error.set('');
    try {
      await this.appointmentsService.cancel(appointment.id);
      this.message.set('Ihr Termin wurde abgesagt.');
      await this.load();
    } catch {
      this.error.set('Dieser Termin kann online nicht mehr abgesagt werden. Bitte kontaktieren Sie Ihre Apotheke.');
    } finally {
      this.isCancelling.set(null);
    }
  }

  protected formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
  }

  private async load(): Promise<void> {
    this.isLoading.set(true);
    try { this.appointments.set(await this.appointmentsService.getMine()); }
    catch { this.error.set('Ihre Termine konnten nicht geladen werden. Bitte versuchen Sie es später erneut.'); }
    finally { this.isLoading.set(false); }
  }
}
