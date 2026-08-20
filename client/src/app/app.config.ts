import { ApplicationConfig, provideAppInitializer, provideBrowserGlobalErrorListeners, inject } from '@angular/core';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { AuthService } from './core/auth/auth.service';
import { provideLucideIcons } from './core/icons/lucide-icons';
import { ThemeService } from './core/theme/theme.service';
import { MockReceiptRepository, ReceiptRepository } from './core/receipts/receipt.repository';
import { MockRewardRepository, RewardRepository } from './core/rewards/reward.repository';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideLucideIcons(),
    provideAppInitializer(() => inject(ThemeService).initialize()),
    AuthService,
    MockReceiptRepository,
    { provide: ReceiptRepository, useExisting: MockReceiptRepository },
    MockRewardRepository,
    { provide: RewardRepository, useExisting: MockRewardRepository },
  ],
};
