import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { Api } from '../../core/api.service';
import { AuthService, errorMessage, fieldErrors } from '../../core/auth.service';
import { AuthHero } from './hero';

/** Shown instantly in the form; the server checks the same rule and is the real gatekeeper. */
const FALLBACK_DOMAINS = ['united.com', 'delta.com', 'aa.com', 'allegiant.com', 'suncountry.com', 'jetblue.com', 'southwest.com'];

@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, AuthHero],
  template: `
    <app-auth-hero />
    <div class="panel">
      <form class="card form-card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h2>Join your crew</h2>
        <p class="sub muted">Use your airline work email to sign up.</p>

        @if (error()) {
          <div class="alert alert-error" role="alert">{{ error() }}</div>
        }

        <div class="row">
          <label class="field">
            <span>First name</span>
            <input class="input" formControlName="first_name" autocomplete="given-name" maxlength="50" />
            @if (serverErrors()['first_name']; as e) { <div class="error">{{ e }}</div> }
          </label>
          <label class="field">
            <span>Last name</span>
            <input class="input" formControlName="last_name" autocomplete="family-name" maxlength="50" />
            @if (serverErrors()['last_name']; as e) { <div class="error">{{ e }}</div> }
          </label>
        </div>

        <label class="field">
          <span>Work email</span>
          <input
            class="input"
            type="email"
            formControlName="email"
            autocomplete="email"
            placeholder="you@southwest.com"
            [class.invalid]="emailHint()"
          />
          @if (emailHint(); as hint) {
            <div class="error">{{ hint }}</div>
          } @else if (serverErrors()['email']; as e) {
            <div class="error">{{ e }}</div>
          }
        </label>

        <label class="field">
          <span>Password</span>
          <input class="input" type="password" formControlName="password" autocomplete="new-password" />
          @if (serverErrors()['password']; as e) {
            <div class="error">{{ e }}</div>
          } @else {
            <div class="faint">At least 8 characters, with letters and numbers.</div>
          }
        </label>
        <label class="field">
          <span>Confirm password</span>
          <input class="input" type="password" formControlName="password_confirmation" autocomplete="new-password" />
          @if (form.hasError('mismatch') && form.controls.password_confirmation.dirty) {
            <div class="error">Passwords don’t match.</div>
          }
        </label>

        <button class="btn btn-block" type="submit" [disabled]="busy() || form.invalid">
          {{ busy() ? 'Creating account…' : 'Create account' }}
        </button>

        <p class="switch muted">Already on Aviate? <a routerLink="/login">Log in</a></p>
      </form>
    </div>
  `,
  styleUrl: './auth.scss',
})
export class RegisterPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private api = inject(Api);

  protected busy = signal(false);
  protected error = signal<string | null>(null);
  protected serverErrors = signal<Record<string, string>>({});

  private domains = toSignal(
    this.api.allowedDomains().pipe(
      map((r) => r.data),
      catchError(() => of(FALLBACK_DOMAINS)),
    ),
    { initialValue: FALLBACK_DOMAINS },
  );

  protected form = inject(FormBuilder).nonNullable.group(
    {
      first_name: ['', [Validators.required, Validators.maxLength(50)]],
      last_name: ['', [Validators.required, Validators.maxLength(50)]],
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: (g: AbstractControl): ValidationErrors | null =>
        g.get('password')?.value === g.get('password_confirmation')?.value ? null : { mismatch: true } },
  );

  private email = toSignal(this.form.controls.email.valueChanges, { initialValue: '' });

  protected emailHint = computed(() => {
    const email = this.email().trim().toLowerCase();
    if (!email.includes('@') || email.endsWith('@')) return null;
    const domain = email.split('@').pop()!;
    if (domain.includes('.') && !this.domains().includes(domain)) {
      return `Aviate is only for airline employees. Use your work email (${this.domains().map((d) => '@' + d).join(', ')}).`;
    }
    return null;
  });

  protected submit(): void {
    if (this.form.invalid || this.busy()) return;
    this.busy.set(true);
    this.error.set(null);
    this.serverErrors.set({});

    this.auth.register(this.form.getRawValue()).subscribe({
      next: () => this.router.navigate(['/']),
      error: (err) => {
        this.busy.set(false);
        const fields = fieldErrors(err);
        this.serverErrors.set(fields);
        if (!Object.keys(fields).length) this.error.set(errorMessage(err, 'Sign-up failed. Please try again.'));
      },
    });
  }
}
