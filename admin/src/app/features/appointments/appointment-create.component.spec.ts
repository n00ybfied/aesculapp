import { TestBed } from '@angular/core/testing';
import { AdminAppointmentService } from '../../core/appointments/admin-appointment.service';
import { AdminCustomerService } from '../../core/customers/admin-customer.service';
import { AppointmentCreateComponent } from './appointment-create.component';

describe('AppointmentCreateComponent', () => {
  it('prefills date and time selected in the calendar', () => {
    TestBed.configureTestingModule({
      providers: [
        { provide: AdminAppointmentService, useValue: {} },
        { provide: AdminCustomerService, useValue: {} },
      ],
    });
    const fixture = TestBed.createComponent(AppointmentCreateComponent);
    fixture.componentRef.setInput('types', []);
    fixture.componentRef.setInput('blocks', []);
    fixture.componentRef.setInput('resources', []);
    fixture.componentRef.setInput('initialDate', '2026-10-01');
    fixture.componentRef.setInput('initialTime', '10:30');
    fixture.detectChanges();

    const date = fixture.nativeElement.querySelector('input[formControlName="date"]') as HTMLInputElement;
    const time = fixture.nativeElement.querySelector('input[formControlName="time"]') as HTMLInputElement;
    expect(date.value).toBe('2026-10-01');
    expect(time.value).toBe('10:30');
  });

  it('does not submit a blocked appointment type even when opened from the calendar', () => {
    const createAppointment = vi.fn();
    TestBed.configureTestingModule({
      providers: [
        { provide: AdminAppointmentService, useValue: { createAppointment } },
        { provide: AdminCustomerService, useValue: {} },
      ],
    });
    const fixture = TestBed.createComponent(AppointmentCreateComponent);
    fixture.componentRef.setInput('types', [{ id: 1, title: 'Beratung', description: null, durationMinutes: 60, bufferMinutes: 0, isVisible: true }]);
    fixture.componentRef.setInput('blocks', [{ id: 2, typeId: 1, startsOn: '2026-10-01', endsOn: '2026-10-01', startsAt: null, endsAt: null, comment: null }]);
    fixture.componentRef.setInput('resources', [{ id: 1, name: 'Gerda', color: '#4b86b0' }]);
    fixture.componentRef.setInput('initialDate', '2026-10-01');
    fixture.componentRef.setInput('initialTime', '10:30');
    fixture.detectChanges();
    fixture.componentInstance['form'].patchValue({ customerMode: 'guest', guestName: 'Testgast', typeId: '1', resourceId: '1' });
    fixture.detectChanges();

    expect((fixture.nativeElement.querySelector('button[type="submit"]') as HTMLButtonElement).disabled).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Dieser Zeitraum ist für die gewählte Terminart gesperrt.');
    expect(createAppointment).not.toHaveBeenCalled();
  });
});
