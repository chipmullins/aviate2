import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthService, errorMessage, fieldErrors } from './auth.service';

describe('AuthService', () => {
  let auth: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    auth = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  it('fetches a CSRF cookie before logging in', () => {
    let done = false;
    auth.login('crew@delta.com', 'secret123', false).subscribe(() => (done = true));

    http.expectOne('/sanctum/csrf-cookie').flush(null);
    const login = http.expectOne('/api/login');
    expect(login.request.method).toBe('POST');
    expect(login.request.body).toEqual({ email: 'crew@delta.com', password: 'secret123', remember: false });
    login.flush({ data: { id: 1, username: 'crew', first_name: 'C', last_name: 'R', full_name: 'C R', email: 'crew@delta.com' } });

    expect(done).toBe(true);
    expect(auth.isLoggedIn()).toBe(true);
  });

  it('treats a failed /api/me as signed out', async () => {
    const result = auth.ensureLoaded();
    http.expectOne('/api/me').flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    expect(await result).toBeNull();
    expect(auth.isLoggedIn()).toBe(false);
  });
});

describe('error helpers', () => {
  it('reads Laravel validation errors', () => {
    const err = new HttpErrorResponse({ status: 422, error: { message: 'Invalid', errors: { email: ['Use your work email.'] } } });
    expect(fieldErrors(err)).toEqual({ email: 'Use your work email.' });
    expect(errorMessage(err)).toBe('Use your work email.');
  });

  it('never shows raw server errors', () => {
    const err = new HttpErrorResponse({ status: 500, error: { message: 'SQLSTATE[42P01]: relation does not exist' } });
    expect(errorMessage(err, 'Oops')).toBe('Oops');
  });
});
