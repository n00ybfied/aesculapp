import { HttpClient, HttpHeaders, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { adminAuthExpiryInterceptor } from './admin-auth-expiry.interceptor';
import { AdminAuthService } from './admin-auth.service';

describe('AdminAuthService', () => {
  let auth: AdminAuthService;
  let http: HttpClient;
  let requests: HttpTestingController;

  const api = 'http://localhost:6080/api/v1';
  const session = (accessToken: string) => ({
    accessToken,
    expiresIn: 900,
    user: { displayName: 'Admin', username: 'admin' },
  });

  beforeEach(() => {
    sessionStorage.setItem('aesculapp.admin.session', JSON.stringify({
      ...session('old-token'),
      expiresAt: Date.now() + 900_000,
    }));
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([adminAuthExpiryInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
    auth = TestBed.inject(AdminAuthService);
    http = TestBed.inject(HttpClient);
    requests = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    requests.verify();
    sessionStorage.clear();
  });

  it('sends only one refresh request for concurrent callers', async () => {
    const first = auth.restoreSession(true);
    const second = auth.restoreSession(true);

    const refresh = requests.expectOne(`${api}/admin/auth/refresh`);
    expect(refresh.request.withCredentials).toBe(true);
    refresh.flush(session('new-token'));

    expect(await Promise.all([first, second])).toEqual([true, true]);
    expect(auth.accessToken()).toBe('new-token');
  });

  it('does not log out on a temporary refresh server error', async () => {
    const refresh = auth.restoreSession(true);
    requests.expectOne(`${api}/admin/auth/refresh`).flush(null, {
      status: 503,
      statusText: 'Service Unavailable',
    });

    expect(await refresh).toBe(false);
    expect(auth.accessToken()).toBe('old-token');
    expect(auth.isAuthenticated()).not.toBeNull();
  });

  it('clears the session when the refresh credential is invalid', async () => {
    const refresh = auth.restoreSession(true);
    requests.expectOne(`${api}/admin/auth/refresh`).flush(null, {
      status: 401,
      statusText: 'Unauthorized',
    });

    expect(await refresh).toBe(false);
    expect(auth.isAuthenticated()).toBeNull();
  });

  it('retries an expired authenticated request with the new token', async () => {
    const result = firstValueFrom(http.get<{ count: number }>(`${api}/admin/chat/open-count`, {
      headers: new HttpHeaders({ Authorization: 'Bearer old-token' }),
    }));
    requests.expectOne(`${api}/admin/chat/open-count`).flush(null, {
      status: 401,
      statusText: 'Unauthorized',
    });

    requests.expectOne(`${api}/admin/auth/refresh`).flush(session('new-token'));
    await new Promise<void>((resolve) => setTimeout(resolve, 0));

    const retry = requests.expectOne(`${api}/admin/chat/open-count`);
    expect(retry.request.headers.get('Authorization')).toBe('Bearer new-token');
    retry.flush({ count: 3 });

    expect(await result).toEqual({ count: 3 });
    expect(auth.accessToken()).toBe('new-token');
  });
});
