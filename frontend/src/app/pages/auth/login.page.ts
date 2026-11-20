import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../core/auth.service';
import { AuthHero } from './hero';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, AuthHero],
  template: `
    <app-auth-hero />
    <div class="panel">
      <form class="card form-card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h2>Welcome back</h2>
        <p class="sub muted">Log in to see what your crew is up to.</p>

        @if (error()) {
          <div class="alert alert-error" role="alert">{{ error() }}</div>
        }

        <label class="field">
          <span>Work email</span>
          <input class="input" type="email" formControlName="email" autocomplete="username" placeholder="you@delta.com" />
        </label>
        <label class="field">
          <span>Password</span>
          <input class="input" type="password" formControlName="password" autocomplete="current-password" />
        </label>
        <label class="check">
          <input type="checkbox" formControlName="remember" /> Keep me signed in
        </label>

        <button class="btn btn-block" type="submit" [disabled]="busy() || form.invalid">
          {{ busy() ? 'Logging in…' : 'Log in' }}
        </button>

        <p class="switch muted">New to Aviate? <a routerLink="/register">Create an account</a></p>
      </form>
    </div>
  `,
  styleUrl: './auth.scss',
})
export class LoginPage {
  private auth = inject(AuthService);
  private router = inject(Router);

  protected busy = signal(false);
  protected error = signal<string | null>(null);

  protected form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
    remember: [false],
  });

  protected submit(): void {
    if (this.form.invalid || this.busy()) return;
    this.busy.set(true);
    this.error.set(null);
    const { email, password, remember } = this.form.getRawValue();

    this.auth.login(email, password, remember).subscribe({
      next: () => this.router.navigate(['/']),
      error: (err) => {
        this.busy.set(false);
        this.error.set(errorMessage(err, 'Login failed. Please try again.'));
      },
    });
  }
}
