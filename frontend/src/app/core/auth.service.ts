import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, firstValueFrom, map, of, switchMap, tap } from 'rxjs';
import { Api } from './api.service';
import { Me } from './models';

/** Holds the signed-in user. The session itself lives in an HttpOnly cookie the app never touches. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private api = inject(Api);
  private router = inject(Router);

  private readonly _user = signal<Me | null>(null);
  private loaded = false;

  readonly user = this._user.asReadonly();
  readonly isLoggedIn = computed(() => this._user() !== null);

  /** Called once on startup (and by guards) to find out whether a session already exists. */
  async ensureLoaded(): Promise<Me | null> {
    if (this.loaded) return this._user();
    const me = await firstValueFrom(
      this.api.me().pipe(
        map((r) => r.data),
        catchError(() => of(null)),
      ),
    );
    this.loaded = true;
    this._user.set(me);
    return me;
  }

  login(email: string, password: string, remember: boolean): Observable<Me> {
    return this.api.csrf().pipe(
      switchMap(() => this.api.login({ email, password, remember })),
      map((r) => r.data),
      tap((me) => this.setUser(me)),
    );
  }

  register(body: Record<string, string>): Observable<Me> {
    return this.api.csrf().pipe(
      switchMap(() => this.api.register(body)),
      map((r) => r.data),
      tap((me) => this.setUser(me)),
    );
  }

  logout(): void {
    this.api.logout().pipe(catchError(() => of(null))).subscribe(() => this.signedOut());
  }

  setUser(me: Me): void {
    this.loaded = true;
    this._user.set(me);
  }

  /** The server says we're no longer signed in (logout, expired session, closed account). */
  signedOut(): void {
    this._user.set(null);
    this.loaded = true;
    this.router.navigate(['/login']);
  }
}

/** Pulls Laravel validation messages out of a 422 response, keyed by field. */
export function fieldErrors(err: unknown): Record<string, string> {
  if (err instanceof HttpErrorResponse && err.error?.errors) {
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(err.error.errors as Record<string, string[]>)) out[k] = v[0];
    return out;
  }
  return {};
}

/** A single human-readable message for any API error. */
export function errorMessage(err: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (err instanceof HttpErrorResponse) {
    if (err.status === 0) return 'Can’t reach the server. Check your connection.';
    if (err.status === 429) return 'You’re doing that too fast. Wait a moment and try again.';
    const first = Object.values(fieldErrors(err))[0];
    if (first) return first;
    if (typeof err.error?.message === 'string' && err.status < 500 && err.error.message) return err.error.message;
  }
  return fallback;
}
