import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, from, switchMap, throwError } from 'rxjs';
import { AdminAuthService } from './admin-auth.service';

export const adminAuthExpiryInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AdminAuthService);
  return next(request).pipe(
    catchError((error: unknown) => {
      const authorization = request.headers.get('Authorization');
      if (!(error instanceof HttpErrorResponse) || error.status !== 401 || !auth.isAuthenticated() || !authorization?.startsWith('Bearer ')) {
        return throwError(() => error);
      }

      const recovery = authorization === `Bearer ${auth.accessToken()}`
        ? auth.expireSession()
        : Promise.resolve(true);

      return from(recovery).pipe(
        switchMap((restored) => restored
          ? next(request.clone({ setHeaders: { Authorization: `Bearer ${auth.accessToken()}` } }))
          : throwError(() => error)),
      );
    }),
  );
};
