import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, CanMatchFn, Router } from '@angular/router';
import { AuthService } from './auth.service';
import { ProfileService } from '../profile/profile.service';

export const authGuard: CanActivateChildFn = (_route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return authService.isAuthenticated() ? true : router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const guestGuard: CanMatchFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return authService.isAuthenticated() ? router.createUrlTree(['/dashboard']) : true;
};

export const authenticatedGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  return auth.isAuthenticated() ? true : router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const customerSetupGuard: CanActivateChildFn = async (_route, state) => {
  const auth = inject(AuthService);
  const profiles = inject(ProfileService);
  const router = inject(Router);
  try {
    const cached = profiles.profile();
    const profile = cached !== null && cached.id === auth.currentUser()?.id ? cached : await profiles.load();
    return profile.setupCompleted ? true : router.createUrlTree(['/einrichtung'], { queryParams: { returnUrl: state.url } });
  } catch {
    return router.createUrlTree(['/einrichtung'], { queryParams: { returnUrl: state.url } });
  }
};
