import { ApplicationConfig, inject, provideAppInitializer, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { adminAuthExpiryInterceptor } from './core/auth/admin-auth-expiry.interceptor';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { TenantBrandingService } from './core/settings/tenant-branding.service';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(withInterceptors([adminAuthExpiryInterceptor])),
    provideRouter(routes),
    provideAppInitializer(() => inject(TenantBrandingService).initializePublicBranding()),
  ]
};
