import { Component, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { Post } from '../core/models';
import { PostCard } from '../shared/post-card.component';

@Component({
  selector: 'app-post-page',
  imports: [PostCard, RouterLink],
  template: `
    <div class="page narrow">
      @if (post(); as p) {
        <app-post-card [post]="p" [expanded]="true" (deleted)="router.navigate(['/'])" />
      } @else if (missing()) {
        <div class="card empty">
          <div class="empty-icon" aria-hidden="true">🧳</div>
          <h2>Post not found</h2>
          <p>It may have been deleted.</p>
          <a class="btn" routerLink="/">Back to feed</a>
        </div>
      } @else {
        <div class="spinner" aria-label="Loading post"></div>
      }
    </div>
  `,
})
export class PostPage {
  private api = inject(Api);
  protected router = inject(Router);
  readonly id = input.required<string>();

  protected post = signal<Post | null>(null);
  protected missing = signal(false);

  constructor() {
    effect(() => {
      const id = Number(this.id());
      untracked(() => {
        this.post.set(null);
        this.missing.set(false);
        if (!Number.isInteger(id) || id <= 0) return this.missing.set(true);
        this.api.post(id).subscribe({ next: (r) => this.post.set(r.data), error: () => this.missing.set(true) });
      });
    });
  }
}
