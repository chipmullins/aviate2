import { Component, effect, inject, input, signal, untracked } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { errorMessage } from '../core/auth.service';
import { SearchResult } from '../core/models';
import { ToastService } from '../core/toast.service';
import { Avatar } from '../shared/avatar';

@Component({
  selector: 'app-search-page',
  imports: [Avatar, RouterLink, FormsModule],
  template: `
    <div class="page narrow">
      <form class="card bar" role="search" (submit)="go($event)">
        <label class="sr-only" for="search-q">Search by name</label>
        <input id="search-q" class="input" type="search" placeholder="Search by name…" [(ngModel)]="draft" name="q" />
        <button class="btn" type="submit">Search</button>
      </form>

      <div class="card">
        @if (loading()) {
          <div class="spinner" aria-label="Searching"></div>
        } @else if (!q()) {
          <p class="muted">Search for pilots, flight attendants and ground crew by name.</p>
        } @else {
          <h2 class="card-title">Results for “{{ q() }}”</h2>
          @for (u of results(); track u.id) {
            <div class="person">
              <a [routerLink]="['/u', u.username]"><app-avatar [user]="u" [size]="48" /></a>
              <div class="person-main">
                <a class="person-name" [routerLink]="['/u', u.username]">{{ u.full_name }}</a>
                <span class="faint">
                  {{ u.airline }}
                  @if (u.mutual_friends_count) { · {{ u.mutual_friends_count }} mutual {{ u.mutual_friends_count === 1 ? 'friend' : 'friends' }} }
                </span>
              </div>
              <div class="person-actions">
                @switch (u.friendship_status) {
                  @case ('friends') { <span class="pill">Friends</span> }
                  @case ('request_sent') { <span class="faint">Request sent</span> }
                  @case ('request_received') { <a class="btn btn-sm" [routerLink]="['/u', u.username]">Respond</a> }
                  @default { <button class="btn btn-sm" type="button" (click)="add(u)">Add friend</button> }
                }
              </div>
            </div>
          } @empty {
            <p class="muted">No one found. Try a first or last name.</p>
          }
        }
      </div>
    </div>
  `,
  styles: `.bar { display: flex; gap: 10px; margin-bottom: 16px; padding: 12px; }`,
})
export class SearchPage {
  private api = inject(Api);
  private router = inject(Router);
  private toast = inject(ToastService);

  /** From the URL: /search?q=... */
  readonly q = input<string>('');

  protected draft = '';
  protected results = signal<SearchResult[]>([]);
  protected loading = signal(false);

  constructor() {
    effect(() => {
      const q = (this.q() ?? '').trim();
      untracked(() => {
        this.draft = q;
        if (!q) return this.results.set([]);
        this.loading.set(true);
        this.api.search(q, 30).subscribe({
          next: (r) => {
            this.results.set(r.data);
            this.loading.set(false);
          },
          error: () => this.loading.set(false),
        });
      });
    });
  }

  protected go(event: Event): void {
    event.preventDefault();
    this.router.navigate(['/search'], { queryParams: { q: this.draft.trim() || null } });
  }

  protected add(u: SearchResult): void {
    this.api.sendFriendRequest(u.username).subscribe({
      next: (r) => this.results.update((list) => list.map((x) => (x.id === u.id ? { ...x, friendship_status: r.friendship_status } : x))),
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }
}
