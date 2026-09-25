import { provideLocationMocks } from '@angular/common/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { API_BASE_URL } from './core/api/api.config';
import { AuthService } from './core/auth/auth.service';
import { CustomerProfile, ProfileService } from './core/profile/profile.service';
import { signal } from '@angular/core';
import { routes } from './app.routes';

describe('App routes', () => {
  let authService: AuthService;
  let httpTesting: HttpTestingController;
  let router: Router;
  const profile = signal<CustomerProfile | null>(null);

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        AuthService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://api.test/api/v1' },
        { provide: ProfileService, useValue: { profile, load: async () => profile(), loadNewsCategories: async () => [] } },
        provideLocationMocks(),
        provideRouter(routes),
      ],
    });

    authService = TestBed.inject(AuthService);
    httpTesting = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    profile.set(null);
  });

  afterEach(() => {
    httpTesting.verify();
  });

  it('navigates an authenticated customer to the scanner', async () => {
    const login = authService.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, email: 'kunde@stadtapotheke-trofaiach.test', displayName: 'Kunde' },
    });
    await login;
    profile.set({ id: 1, setupCompleted: true } as CustomerProfile);

    await router.navigateByUrl('/scanner');

    expect(router.url).toBe('/scanner');
  });

  it('redirects a new customer to the one-time setup', async () => {
    const login = authService.login({ email: 'kunde@stadtapotheke-trofaiach.test', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').flush({
      accessToken: 'access-token', tokenType: 'Bearer', expiresIn: 900,
      user: { id: 1, email: 'kunde@stadtapotheke-trofaiach.test', displayName: 'Neuer Kunde' },
    });
    await login;
    profile.set({ id: 1, setupCompleted: false } as CustomerProfile);

    await router.navigateByUrl('/dashboard');

    expect(router.url).toBe('/einrichtung?returnUrl=%2Fdashboard');
  });
});
