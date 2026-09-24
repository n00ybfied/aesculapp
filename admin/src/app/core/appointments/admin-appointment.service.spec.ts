import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AdminAppointmentService } from './admin-appointment.service';

describe('AdminAppointmentService', () => {
  let service: AdminAppointmentService;
  let requests: HttpTestingController;
  const api = 'http://localhost:6080/api/v1/admin/appointments';

  beforeEach(() => {
    sessionStorage.setItem('aesculapp.admin.session', JSON.stringify({
      accessToken: 'test-token',
      expiresIn: 900,
      expiresAt: Date.now() + 900_000,
      user: { id: 1, displayName: 'Admin', username: 'admin' },
      roles: ['ROLE_TENANT_ADMIN'],
      permissions: ['appointments'],
    }));
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    service = TestBed.inject(AdminAppointmentService);
    requests = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    requests.verify();
    sessionStorage.clear();
  });

  it('loads only the appointment list for a poll', async () => {
    const pending = service.listAppointments();
    const request = requests.expectOne(api);
    expect(request.request.headers.get('Authorization')).toBe('Bearer test-token');
    request.flush({ appointments: [{ id: 12, customer: 'Kunde', type: 'Beratung', resource: 'Gerda', startsAt: '2026-09-25T10:00:00+02:00', endsAt: '2026-09-25T10:30:00+02:00', status: 'reserved' }] });
    expect((await pending).map((appointment) => appointment.id)).toEqual([12]);
  });

  it('updates the menu count when the viewed list is acknowledged', async () => {
    const count = service.refreshUnseenCount();
    requests.expectOne(`${api}/unseen-count`).flush({ count: 2 });
    await count;
    expect(service.unseenCount()).toBe(2);

    const seen = service.markSeen(12);
    const request = requests.expectOne(`${api}/seen`);
    expect(request.request.body).toEqual({ throughId: 12 });
    request.flush({ count: 0 });
    await seen;
    expect(service.unseenCount()).toBe(0);
  });

  it('sends a manual guest booking without a customer account', async () => {
    const data = {
      typeId: 3,
      resourceId: 2,
      date: '2026-10-01',
      time: '10:00',
      customerId: null,
      guestName: 'Gast Name',
      note: '',
    };
    const pending = service.createAppointment(data);
    const request = requests.expectOne(api);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual(data);
    request.flush({ appointment: { id: 1, customer: 'Gast Name', customerId: null } });
    expect((await pending).appointment.customerId).toBeNull();
  });

  it('loads and acknowledges customer cancellation notices', async () => {
    const pending = service.listCustomerCancellations();
    requests.expectOne(`${api}/customer-cancellations`).flush({ cancellations: [{ id: 8, appointmentId: 21, customer: 'Testkunde', startsAt: '2026-10-01T10:00:00+02:00', occurredAt: '2026-09-24T12:00:00+02:00' }] });
    expect((await pending).map((notice) => notice.id)).toEqual([8]);

    const acknowledge = service.acknowledgeCustomerCancellations(8);
    const request = requests.expectOne(`${api}/customer-cancellations/acknowledge`);
    expect(request.request.body).toEqual({ throughId: 8 });
    request.flush({ acknowledgedThroughId: 8 });
    await acknowledge;
  });

  it('sends administrator confirmations through the dedicated endpoint', async () => {
    const pending = service.confirm(42);
    const request = requests.expectOne(`${api}/42/confirm`);
    expect(request.request.method).toBe('POST');
    expect(request.request.headers.get('Authorization')).toBe('Bearer test-token');
    request.flush({ appointment: { id: 42, status: 'reserved' } });
    await pending;
  });
});
