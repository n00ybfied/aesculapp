import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { AdminAuthService } from '../auth/admin-auth.service';
import { StaffAppointmentService } from './staff-appointment.service';

describe('StaffAppointmentService', () => {
  let service: StaffAppointmentService;
  let requests: HttpTestingController;
  const api = 'http://localhost:6080/api/v1/admin/appointments/mine';

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AdminAuthService, useValue: { accessToken: () => 'test-token' } },
      ],
    });
    service = TestBed.inject(StaffAppointmentService);
    requests = TestBed.inject(HttpTestingController);
  });

  afterEach(() => requests.verify());

  it('loads one aggregated appointment alert', async () => {
    const pending = service.refreshAlert();
    const request = requests.expectOne(`${api}/alert`);
    expect(request.request.headers.get('Authorization')).toBe('Bearer test-token');
    request.flush({ mode: 'confirmation', count: 3 });
    await pending;
    expect(service.alert()).toEqual({ mode: 'confirmation', count: 3 });
  });

  it('marks own appointments as seen and reloads the new-appointment alert', async () => {
    const seen = service.markSeen(42);
    const request = requests.expectOne(`${api}/seen`);
    expect(request.request.body).toEqual({ throughId: 42 });
    request.flush({ seenThroughId: 42 });
    await Promise.resolve();
    const alert = requests.expectOne(`${api}/alert`);
    alert.flush({ mode: 'new', count: 0 });
    await seen;
    expect(service.alert()).toEqual({ mode: 'new', count: 0 });
  });
});
