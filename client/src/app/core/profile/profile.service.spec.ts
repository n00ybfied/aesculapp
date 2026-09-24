import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { CustomerProfile, ProfileService } from './profile.service';

describe('ProfileService account deletion', () => {
  afterEach(() => {
    localStorage.removeItem('aesculapp.mock-rewards.v1');
    localStorage.removeItem('aesculapp.push-prompt.v1.123');
    localStorage.removeItem('aesculapp.push-categories-hint.v1.123');
    localStorage.removeItem('aesculapp.app-notice.v1.test.123');
    localStorage.removeItem('unrelated-key');
    TestBed.inject(HttpTestingController).verify();
  });

  it('sends the password and removes only known local customer data after success', async () => {
    TestBed.configureTestingModule({
      providers: [
        ProfileService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: '/api/v1' },
        { provide: AuthService, useValue: { accessToken: signal('test-token') } },
      ],
    });
    const service = TestBed.inject(ProfileService);
    service.profile.set({ id: 123 } as CustomerProfile);
    localStorage.setItem('aesculapp.mock-rewards.v1', 'private history');
    localStorage.setItem('aesculapp.push-prompt.v1.123', 'seen');
    localStorage.setItem('aesculapp.push-categories-hint.v1.123', 'seen');
    localStorage.setItem('aesculapp.app-notice.v1.test.123', 'seen');
    localStorage.setItem('unrelated-key', 'keep');

    const deletion = service.deleteAccount('correct-password');
    const request = TestBed.inject(HttpTestingController).expectOne('/api/v1/profile');
    expect(request.request.method).toBe('DELETE');
    expect(request.request.body).toEqual({ password: 'correct-password' });
    expect(request.request.headers.get('Authorization')).toBe('Bearer test-token');
    request.flush(null, { status: 204, statusText: 'No Content' });
    await deletion;

    expect(service.profile()).toBeNull();
    expect(localStorage.getItem('aesculapp.mock-rewards.v1')).toBeNull();
    expect(localStorage.getItem('aesculapp.push-prompt.v1.123')).toBeNull();
    expect(localStorage.getItem('aesculapp.push-categories-hint.v1.123')).toBeNull();
    expect(localStorage.getItem('aesculapp.app-notice.v1.test.123')).toBeNull();
    expect(localStorage.getItem('unrelated-key')).toBe('keep');
  });

  it('keeps profile and local data when deletion is rejected', async () => {
    TestBed.configureTestingModule({
      providers: [
        ProfileService,
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: API_BASE_URL, useValue: '/api/v1' },
        { provide: AuthService, useValue: { accessToken: signal('test-token') } },
      ],
    });
    const service = TestBed.inject(ProfileService);
    service.profile.set({ id: 123 } as CustomerProfile);
    localStorage.setItem('aesculapp.mock-rewards.v1', 'private history');

    const deletion = service.deleteAccount('wrong');
    TestBed.inject(HttpTestingController).expectOne('/api/v1/profile').flush(
      { message: 'Wrong password' }, { status: 422, statusText: 'Unprocessable Content' },
    );
    await expect(deletion).rejects.toBeDefined();
    expect(service.profile()?.id).toBe(123);
    expect(localStorage.getItem('aesculapp.mock-rewards.v1')).toBe('private history');
  });
});
