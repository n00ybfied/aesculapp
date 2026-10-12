import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        AuthService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://api.test/api/v1' },
      ],
    });
    service = TestBed.inject(AuthService);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpTesting.verify();
  });

  it('authenticates the customer through the API', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    request.flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, email: 'kunde@stadtapotheke-trofaiach.test', displayName: 'Kunde' },
    });

    expect(await login).toBe('success');
    expect(service.currentUser()?.email).toBe('kunde@stadtapotheke-trofaiach.test');
    expect(service.accessToken()).toBe('access-token');
    expect(service.isAuthenticated()).toBe(true);
  });

  it('rejects invalid credentials', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'falsch' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    request.flush({ message: 'Invalid credentials.' }, { status: 401, statusText: 'Unauthorized' });

    expect(await login).toBe('invalid-credentials');
    expect(service.currentUser()).toBeNull();
    expect(service.isAuthenticated()).toBe(false);
  });

  it('reports when the API cannot be reached', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').error(new ProgressEvent('error'));

    expect(await login).toBe('connection-error');
    expect(service.isAuthenticated()).toBe(false);
  });

  it('distinguishes server errors from invalid credentials', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').flush(null, { status: 500, statusText: 'Internal Server Error' });

    expect(await login).toBe('server-error');
  });

  it('clears the session on logout', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    request.flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, email: 'kunde@stadtapotheke-trofaiach.test', displayName: 'Kunde' },
    });
    await login;

    service.logout();

    const logoutRequest = httpTesting.expectOne('http://api.test/api/v1/auth/logout');
    expect(logoutRequest.request.method).toBe('POST');
    expect(logoutRequest.request.withCredentials).toBe(true);
    logoutRequest.flush(null);

    expect(service.currentUser()).toBeNull();
    expect(service.accessToken()).toBeNull();
  });

  it('returns to guest mode after a successful password reset', async () => {
    const login = service.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').flush({
      accessToken: 'access-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 1, email: 'kunde@stadtapotheke-trofaiach.test', displayName: 'Kunde' },
    });
    await login;

    const reset = service.resetPassword('a'.repeat(64), 'neues-passwort');
    httpTesting.expectOne('http://api.test/api/v1/auth/password-reset/confirm').flush(null);

    expect(await reset).toBe('success');
    expect(service.isAuthenticated()).toBe(false);
    expect(service.accessToken()).toBeNull();
  });
});
