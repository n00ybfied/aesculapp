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
    const login = service.login({ username: 'kunde', password: 'trofaiach' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ username: 'kunde', password: 'trofaiach' });
    request.flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, username: 'kunde', displayName: 'Kunde' },
    });

    expect(await login).toBe(true);
    expect(service.currentUser()?.username).toBe('kunde');
    expect(service.accessToken()).toBe('access-token');
    expect(service.isAuthenticated()).toBe(true);
  });

  it('rejects invalid credentials', async () => {
    const login = service.login({ username: 'kunde', password: 'falsch' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    request.flush({ message: 'Invalid credentials.' }, { status: 401, statusText: 'Unauthorized' });

    expect(await login).toBe(false);
    expect(service.currentUser()).toBeNull();
    expect(service.isAuthenticated()).toBe(false);
  });

  it('clears the session on logout', async () => {
    const login = service.login({ username: 'kunde', password: 'trofaiach' });
    const request = httpTesting.expectOne('http://api.test/api/v1/auth/login');
    request.flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, username: 'kunde', displayName: 'Kunde' },
    });
    await login;

    service.logout();

    expect(service.currentUser()).toBeNull();
    expect(service.accessToken()).toBeNull();
  });
});
