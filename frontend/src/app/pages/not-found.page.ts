import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink],
  template: `
    <div class="page narrow">
      <div class="card empty">
        <div class="empty-icon" aria-hidden="true">🗺️</div>
        <h2>Off the flight plan</h2>
        <p>We couldn’t find that page.</p>
        <a class="btn" routerLink="/">Back to feed</a>
      </div>
    </div>
  `,
})
export class NotFoundPage {}
