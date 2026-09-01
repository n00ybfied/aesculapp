import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AdminAuthService } from './admin-auth.service';

export const adminAuthExpiryInterceptor: HttpInterceptorFn = (request, next) => {
  const auth = inject(AdminAuthService);
  return next(request).pipe(catchError((error: unknown) => {
    if (error instanceof HttpErrorResponse && error.status === 401 && auth.isAuthenticated()) auth.expireSession();
    return throwError(() => error);
  }));
};
