import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../core/auth.service';
import { DemoService } from '../../core/demo.service';
import { Avatar } from '../../shared/avatar';
import { AuthHero } from './hero';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, AuthHero, Avatar],
  template: `
    <app-auth-hero />
    <div class="panel">
      <div class="column">
      @if (demo.enabled()) {
        <section class="card demo-card" aria-labelledby="demo-title">
          <h2 id="demo-title">Just looking? Try the demo</h2>
          <p class="sub muted">Sign in as one of our sample crew with one click. Everything resets {{ demo.resetLabel() }}.</p>
          @for (a of demo.info().accounts; track a.id) {
            <button class="demo-account" type="button" (click)="tryDemo(a.username)" [disabled]="busy()">
              <app-avatar [user]="a" [size]="44" />
              <span class="demo-text">
                <strong>{{ a.full_name }}</strong>
                <span class="faint">{{ a.blurb }}</span>
              </span>
              <span class="go" aria-hidden="true">→</span>
            </button>
          }
        </section>
      }
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
    </div>
  `,
  styleUrl: './auth.scss',
  styles: `
    .column { width: 100%; max-width: 420px; display: flex; flex-direction: column; gap: 16px; }
    .demo-card { padding: 24px; border: 2px solid var(--amber); background: linear-gradient(180deg, var(--amber-soft), var(--surface) 60%); }
    .demo-card h2 { font-size: 1.2rem; }
    .demo-card .sub { margin: 0 0 14px; font-size: 0.92rem; }
    .demo-account {
      display: flex; align-items: center; gap: 12px; width: 100%; text-align: left;
      background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-sm);
      padding: 10px 12px; margin-top: 8px; cursor: pointer; font: inherit; color: var(--ink);
      transition: border-color .15s, box-shadow .15s;
    }
    .demo-account:hover:not(:disabled) { border-color: var(--sky); box-shadow: var(--shadow); }
    .demo-account:disabled { opacity: .6; cursor: wait; }
    .demo-text { flex: 1; display: flex; flex-direction: column; }
    .demo-text strong { font-family: var(--font-display); font-weight: 600; }
    .go { color: var(--sky); font-size: 1.2rem; }
  `,
})
export class LoginPage {
  protected demo = inject(DemoService);
  private auth = inject(AuthService);
  private router = inject(Router);

  protected busy = signal(false);
  protected error = signal<string | null>(null);

  protected form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
    remember: [false],
  });

  protected tryDemo(username: string): void {
    this.busy.set(true);
    this.error.set(null);
    this.auth.demoLogin(username).subscribe({
      next: () => this.router.navigate(['/']),
      error: (err) => {
        this.busy.set(false);
        this.error.set(errorMessage(err, 'The demo couldn’t start. Please try again.'));
      },
    });
  }

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
