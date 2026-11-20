import { Component } from '@angular/core';

/** The navy brand panel beside the login and sign-up forms. */
@Component({
  selector: 'app-auth-hero',
  template: `
    <div class="brand">
      <svg viewBox="0 0 32 32" width="36" height="36" aria-hidden="true">
        <circle cx="16" cy="16" r="15" fill="#f5a524" />
        <path d="M6 18.5 26 9l-5.2 15.2-3.6-5.8-5.6 2.4 2.6-4.2L6 18.5Z" fill="#0b1b3a" />
      </svg>
      Aviate
    </div>
    <div>
      <h1>The crew room, wherever you land.</h1>
      <p>
        A private network for pilots, flight attendants and ground crew at North American airlines.
        Sign up with your airline email to keep up with the people you fly with.
      </p>
      <div class="airlines" aria-label="Airlines on Aviate">
        <span>United</span><span>Delta</span><span>American</span><span>Southwest</span>
        <span>JetBlue</span><span>Allegiant</span><span>Sun Country</span>
      </div>
    </div>
    <p class="faint" style="color: rgba(255,255,255,.55)">Only verified airline domains can join.</p>
    <svg class="trail" viewBox="0 0 520 200" aria-hidden="true">
      <path d="M0 180 C 160 170, 300 110, 500 20" stroke="#fff" stroke-width="3" fill="none" stroke-dasharray="10 12" />
      <path d="M470 30 500 20 488 50" stroke="#fff" stroke-width="3" fill="none" />
    </svg>
  `,
  styleUrl: './hero.scss',
})
export class AuthHero {}
