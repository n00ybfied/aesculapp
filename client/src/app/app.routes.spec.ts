import { provideLocationMocks } from '@angular/common/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { API_BASE_URL } from './core/api/api.config';
import { AuthService } from './core/auth/auth.service';
import { routes } from './app.routes';

describe('App routes', () => {
  let authService: AuthService;
  let httpTesting: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        AuthService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: 'http://api.test/api/v1' },
        provideLocationMocks(),
        provideRouter(routes),
      ],
    });

    authService = TestBed.inject(AuthService);
    httpTesting = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
  });

  afterEach(() => {
    httpTesting.verify();
  });

  it('navigates an authenticated customer to the scanner', async () => {
    const login = authService.login({ username: 'kunde', password: 'trofaiach' });
    httpTesting.expectOne('http://api.test/api/v1/auth/login').flush({
      accessToken: 'access-token',
      tokenType: 'Bearer',
      expiresIn: 900,
      user: { id: 1, username: 'kunde', displayName: 'Kunde' },
    });
    await login;

    await router.navigateByUrl('/scanner');

    expect(router.url).toBe('/scanner');
  });
});
