import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, switchMap, throwError } from 'rxjs';
import { Api } from './api.service';
import { AuthService } from './auth.service';

/**
 * - 419 means the CSRF token expired: fetch a fresh one and retry the request once.
 * - 401 on anything but the "who am I" check means the session ended: go to login.
 */
export const apiInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const api = inject(Api);

  return next(req).pipe(
    catchError((err: unknown) => {
      if (!(err instanceof HttpErrorResponse)) return throwError(() => err);

      if (err.status === 419 && !req.headers.has('X-Retried')) {
        return api.csrf().pipe(switchMap(() => next(req.clone({ setHeaders: { 'X-Retried': '1' } }))));
      }

      if (err.status === 401 && !req.url.endsWith('/api/me') && auth.isLoggedIn()) {
        auth.signedOut();
      }

      return throwError(() => err);
    }),
  );
};
