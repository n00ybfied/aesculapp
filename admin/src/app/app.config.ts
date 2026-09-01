import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { adminAuthExpiryInterceptor } from './core/auth/admin-auth-expiry.interceptor';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(withInterceptors([adminAuthExpiryInterceptor])),
    provideRouter(routes)
  ]
};
