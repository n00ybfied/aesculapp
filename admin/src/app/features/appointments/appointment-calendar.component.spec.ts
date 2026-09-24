import { TestBed } from '@angular/core/testing';
import type { AdminAppointment } from '../../core/appointments/admin-appointment.service';
import { AppointmentCalendarComponent } from './appointment-calendar.component';
import { calendarDays, pharmacyDateKey, shiftCalendarDate } from './appointment-calendar.layout';

describe('AppointmentCalendarComponent', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: () => ({ matches: false }),
    });
  });

  it('opens the create flow with the clicked day and half-hour time', () => {
    const fixture = TestBed.createComponent(AppointmentCalendarComponent);
    fixture.componentRef.setInput('appointments', []);
    fixture.componentRef.setInput('blocks', []);
    fixture.componentRef.setInput('types', []);
    const requested: { date: string; time: string }[] = [];
    fixture.componentInstance.createRequested.subscribe((value) => requested.push(value));
    fixture.detectChanges();

    (fixture.nativeElement.querySelector('button[aria-label="Nächste Woche"]') as HTMLButtonElement).click();
    fixture.detectChanges();
    const firstDay = calendarDays(shiftCalendarDate(pharmacyDateKey(new Date()), 7), 'week')[0];
    const slot = fixture.nativeElement.querySelectorAll('.calendar-slot')[1] as HTMLButtonElement;
    slot.click();

    expect(requested).toEqual([{ date: firstDay, time: '08:30' }]);
  });

  it('keeps existing appointment blocks bound to their detail action', () => {
    const day = calendarDays(pharmacyDateKey(new Date()), 'week')[0];
    const appointment: AdminAppointment = {
      id: 10,
      customer: 'Testkunde',
      customerId: 2,
      type: 'Beratung',
      resource: 'Gerda',
      resourceId: 1,
      resourceColor: '#4b86b0',
      startsAt: `${day}T10:00:00+02:00`,
      endsAt: `${day}T10:30:00+02:00`,
      status: 'reserved',
      note: null,
    };
    const fixture = TestBed.createComponent(AppointmentCalendarComponent);
    fixture.componentRef.setInput('appointments', [appointment]);
    fixture.componentRef.setInput('blocks', []);
    fixture.componentRef.setInput('types', []);
    const selected: AdminAppointment[] = [];
    const requested: { date: string; time: string }[] = [];
    fixture.componentInstance.selected.subscribe((value) => selected.push(value));
    fixture.componentInstance.createRequested.subscribe((value) => requested.push(value));
    fixture.detectChanges();

    (fixture.nativeElement.querySelector('.calendar-event') as HTMLButtonElement).click();

    expect(selected).toEqual([appointment]);
    expect(requested).toEqual([]);
  });
});
