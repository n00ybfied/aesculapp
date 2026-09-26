import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { AdminAuthService } from '../auth/admin-auth.service';
import { TenantBrandingService } from './tenant-branding.service';

describe('TenantBrandingService', () => {
  it('removes SMTP settings through the protected endpoint and updates local state', async () => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AdminAuthService, useValue: { accessToken: () => 'admin-token' } },
      ],
    });
    const service = TestBed.inject(TenantBrandingService);
    const requests = TestBed.inject(HttpTestingController);
    const result = service.clearSmtp();
    const request = requests.expectOne('http://localhost:6080/api/v1/admin/settings/branding/smtp');
    expect(request.request.method).toBe('DELETE');
    expect(request.request.headers.get('Authorization')).toBe('Bearer admin-token');

    const cleared = { ...service.branding(), smtpHost: null, smtpPort: null, smtpEncryption: null, smtpUsername: null, smtpFrom: null, smtpPasswordConfigured: false };
    request.flush({ branding: cleared });
    expect(await result).toEqual(cleared);
    expect(service.branding().smtpPasswordConfigured).toBe(false);
    requests.verify();
  });
});
