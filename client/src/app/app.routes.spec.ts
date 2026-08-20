import { provideLocationMocks } from '@angular/common/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { AuthService } from './core/auth/auth.service';
import { routes } from './app.routes';

describe('App routes', () => {
  let authService: AuthService;
  let router: Router;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [AuthService, provideLocationMocks(), provideRouter(routes)],
    });

    authService = TestBed.inject(AuthService);
    router = TestBed.inject(Router);
  });

  it('navigates an authenticated customer to the scanner', async () => {
    authService.login({ username: 'kunde', password: 'trofaiach' });

    await router.navigateByUrl('/scanner');

    expect(router.url).toBe('/scanner');
  });
});
