import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { AdminAuthService } from '../auth/admin-auth.service';
import { AdminRedemptionService } from './admin-redemption.service';

describe('AdminRedemptionService', () => {
  const api = 'http://localhost:6080/api/v1/admin';
  let service: AdminRedemptionService;
  let requests: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [
      provideHttpClient(),
      provideHttpClientTesting(),
      { provide: AdminAuthService, useValue: { accessToken: () => 'test-token' } },
    ] });
    service = TestBed.inject(AdminRedemptionService);
    requests = TestBed.inject(HttpTestingController);
  });

  afterEach(() => requests.verify());

  it('counts active reward and coupon redemptions together', async () => {
    const loading = service.refresh();
    const duplicatePoll = service.refresh();
    const rewards = requests.expectOne(`${api}/redemptions/active`);
    const coupons = requests.expectOne(`${api}/coupons/redemptions/active`);
    expect(rewards.request.headers.get('Authorization')).toBe('Bearer test-token');
    expect(coupons.request.headers.get('Authorization')).toBe('Bearer test-token');
    rewards.flush({ redemptions: [{ id: 1 }, { id: 2 }] });
    coupons.flush({ redemptions: [{ id: 'coupon-1' }] });
    await Promise.all([loading, duplicatePoll]);

    expect(service.activeCount()).toBe(3);
    expect(service.error()).toBe('');

    const cancelled = service.cancelReward(1);
    requests.expectOne(`${api}/redemptions/1/cancel`).flush({});
    await cancelled;
    expect(service.activeCount()).toBe(2);

    const cancelledCoupon = service.cancelCoupon('coupon-1');
    requests.expectOne(`${api}/coupons/redemptions/coupon-1/cancel`).flush({});
    await cancelledCoupon;
    expect(service.activeCount()).toBe(1);

    service.clear();
    expect(service.activeCount()).toBeNull();
  });

  it('hides a stale count when polling fails and recovers on the next poll', async () => {
    const failed = service.refresh();
    requests.expectOne(`${api}/coupons/redemptions/active`).flush({ redemptions: [] });
    requests.expectOne(`${api}/redemptions/active`).flush({ message: 'Unavailable' }, { status: 503, statusText: 'Service Unavailable' });
    await failed;
    expect(service.activeCount()).toBeNull();
    expect(service.error()).not.toBe('');

    const retry = service.refresh();
    requests.expectOne(`${api}/redemptions/active`).flush({ redemptions: [] });
    requests.expectOne(`${api}/coupons/redemptions/active`).flush({ redemptions: [{ id: 'coupon-2' }] });
    await retry;
    expect(service.activeCount()).toBe(1);
    expect(service.error()).toBe('');
  });
});
