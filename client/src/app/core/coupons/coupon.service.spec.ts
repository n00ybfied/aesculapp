import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { CouponService } from './coupon.service';

describe('CouponService', () => {
  let service: CouponService;
  let http: HttpTestingController;
  const api = 'http://localhost:6080/api/v1';

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [
      CouponService,
      { provide: API_BASE_URL, useValue: api },
      { provide: AuthService, useValue: { accessToken: () => 'customer-token' } },
      provideHttpClient(),
      provideHttpClientTesting(),
    ] });
    service = TestBed.inject(CouponService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('authenticates the catalog, redemption and active-redemption requests', async () => {
    const list = service.list();
    const listRequest = http.expectOne(`${api}/coupons`);
    expect(listRequest.request.headers.get('Authorization')).toBe('Bearer customer-token');
    listRequest.flush({ coupons: [] });
    await list;

    const redemption = service.redeem([12, 13]);
    const redeemRequest = http.expectOne(`${api}/coupons/redeem`);
    expect(redeemRequest.request.headers.get('Authorization')).toBe('Bearer customer-token');
    expect(redeemRequest.request.body).toEqual({ couponIds: [12, 13] });
    redeemRequest.flush({ redemption: { id: 'a'.repeat(32), items: [], summary: 'Test', validUntil: '2026-09-16T12:00:00+00:00' } });
    await redemption;

    const active = service.active();
    const activeRequest = http.expectOne(`${api}/coupons/redemptions/active`);
    expect(activeRequest.request.headers.get('Authorization')).toBe('Bearer customer-token');
    activeRequest.flush({ redemption: null });
    await active;
  });
});
