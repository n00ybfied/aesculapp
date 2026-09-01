import { ApplicationConfig, provideAppInitializer, provideBrowserGlobalErrorListeners, inject } from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { API_BASE_URL, apiBaseUrl } from './core/api/api.config';
import { AuthService } from './core/auth/auth.service';
import { authExpiryInterceptor } from './core/auth/auth-expiry.interceptor';
import { provideLucideIcons } from './core/icons/lucide-icons';
import { ThemeService } from './core/theme/theme.service';
import { MockReceiptRepository, ReceiptRepository } from './core/receipts/receipt.repository';
import { MockRewardRepository, RewardRepository } from './core/rewards/reward.repository';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(withInterceptors([authExpiryInterceptor])),
    provideRouter(routes),
    { provide: API_BASE_URL, useValue: apiBaseUrl },
    provideLucideIcons(),
    provideAppInitializer(() => inject(ThemeService).initialize()),
    provideAppInitializer(() => inject(AuthService).restoreSession()),
    AuthService,
    MockReceiptRepository,
    { provide: ReceiptRepository, useExisting: MockReceiptRepository },
    MockRewardRepository,
    { provide: RewardRepository, useExisting: MockRewardRepository },
  ],
};
