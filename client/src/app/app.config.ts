import { ApplicationConfig, provideAppInitializer, provideBrowserGlobalErrorListeners, inject } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { API_BASE_URL, localApiBaseUrl } from './core/api/api.config';
import { AuthService } from './core/auth/auth.service';
import { provideLucideIcons } from './core/icons/lucide-icons';
import { ThemeService } from './core/theme/theme.service';
import { MockReceiptRepository, ReceiptRepository } from './core/receipts/receipt.repository';
import { MockRewardRepository, RewardRepository } from './core/rewards/reward.repository';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideHttpClient(),
    provideRouter(routes),
    { provide: API_BASE_URL, useValue: localApiBaseUrl },
    provideLucideIcons(),
    provideAppInitializer(() => inject(ThemeService).initialize()),
    AuthService,
    MockReceiptRepository,
    { provide: ReceiptRepository, useExisting: MockReceiptRepository },
    MockRewardRepository,
    { provide: RewardRepository, useExisting: MockRewardRepository },
  ],
};
