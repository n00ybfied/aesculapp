import { inject } from '@angular/core';
import { CanActivateChildFn, Router } from '@angular/router';
import { AdminAuthService } from './admin-auth.service';

export const adminAreaGuard: CanActivateChildFn = (route) => {
  const auth = inject(AdminAuthService);
  const router = inject(Router);
  const path = route.routeConfig?.path?.split('/')[0];
  const area = ({ dashboard: 'dashboard', slider: 'slider', statistik: 'statistics', chat: 'chat', inhalte: 'news', medien: 'media', praemien: 'rewards', gutscheine: 'coupons', termine: 'appointments', einloesungen: 'redemptions', kunden: 'customers', benutzer: 'users', einstellungen: 'settings', nachrichtenvorlagen: 'settings' } as Record<string, string>)[path ?? ''];
  if (!area || auth.canAccess(area)) return true;
  return router.createUrlTree(['/meine-termine']);
};
