import { inject } from '@angular/core';
import { CanActivateChildFn, CanMatchFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateChildFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return authService.isAuthenticated() ? true : router.createUrlTree(['/login']);
};

export const guestGuard: CanMatchFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return authService.isAuthenticated() ? router.createUrlTree(['/dashboard']) : true;
};
