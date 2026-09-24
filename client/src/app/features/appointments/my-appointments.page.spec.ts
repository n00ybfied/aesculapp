import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AppointmentService, type CustomerAppointment } from '../../core/appointments/appointment.service';
import { MyAppointmentsPage } from './my-appointments.page';

describe('MyAppointmentsPage', () => {
  it('shows upcoming appointments but hides completed past appointments', async () => {
    const past: CustomerAppointment = {
      id: 1,
      type: 'Vergangene Beratung',
      resource: 'Gerda',
      startsAt: new Date(Date.now() - 3_600_000).toISOString(),
      endsAt: new Date(Date.now() - 1_800_000).toISOString(),
      status: 'reserved',
    };
    const upcoming: CustomerAppointment = {
      id: 2,
      type: 'Kommende Beratung',
      resource: 'Gerda',
      startsAt: new Date(Date.now() + 3_600_000).toISOString(),
      endsAt: new Date(Date.now() + 5_400_000).toISOString(),
      status: 'reserved',
    };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AppointmentService, useValue: { getMine: async () => [past, upcoming] } },
      ],
    });

    const fixture = TestBed.createComponent(MyAppointmentsPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Kommende Beratung');
    expect(fixture.nativeElement.textContent).not.toContain('Vergangene Beratung');
  });
});
