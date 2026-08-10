import { TestBed } from '@angular/core/testing';
import { AuthService } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [AuthService] });
    service = TestBed.inject(AuthService);
  });

  it('authenticates the dummy customer', () => {
    const success = service.login({ username: 'kunde', password: 'trofaiach' });

    expect(success).toBe(true);
    expect(service.currentUser()?.username).toBe('kunde');
    expect(service.isAuthenticated()).toBe(true);
  });

  it('rejects invalid credentials', () => {
    const success = service.login({ username: 'kunde', password: 'falsch' });

    expect(success).toBe(false);
    expect(service.currentUser()).toBeNull();
    expect(service.isAuthenticated()).toBe(false);
  });

  it('clears the session on logout', () => {
    service.login({ username: 'kunde', password: 'trofaiach' });

    service.logout();

    expect(service.currentUser()).toBeNull();
  });
});
