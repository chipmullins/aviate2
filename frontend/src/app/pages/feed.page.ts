import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { FriendRequest, Post, Profile, Trend } from '../core/models';
import { Avatar } from '../shared/avatar';
import { InfiniteScroll } from '../shared/infinite-scroll.directive';
import { PostCard } from '../shared/post-card.component';
import { PostComposer } from '../shared/post-composer.component';
import { CursorList } from './feed-list';

@Component({
  selector: 'app-feed-page',
  imports: [PostComposer, PostCard, Avatar, RouterLink, InfiniteScroll],
  template: `
    <div class="page grid-3">
      <aside class="side sticky">
        @if (auth.user(); as me) {
          <div class="card me">
            <a [routerLink]="['/u', me.username]"><app-avatar [user]="me" [size]="72" /></a>
            <a class="name" [routerLink]="['/u', me.username]">{{ me.full_name }}</a>
            @if (me.airline) { <span class="pill">{{ me.airline }}</span> }
            @if (profile(); as p) {
              <dl class="stats">
                <div><dt>Posts</dt><dd>{{ p.posts_count }}</dd></div>
                <div><dt>Friends</dt><dd>{{ p.friends_count }}</dd></div>
                <div><dt>Likes</dt><dd>{{ p.likes_received }}</dd></div>
              </dl>
            }
          </div>
        }
      </aside>

      <section aria-label="News feed" class="stack">
        <app-post-composer (created)="feed.prepend($event)" />

        @for (post of feed.items(); track post.id) {
          <app-post-card [post]="post" (deleted)="feed.remove($event)" />
        } @empty {
          @if (!feed.loading()) {
            <div class="card empty">
              <div class="empty-icon" aria-hidden="true">🛫</div>
              <h3>Your feed is clear for takeoff</h3>
              <p>Find people you fly with and add them as friends to see their posts here.</p>
              <a class="btn" routerLink="/search">Find crew</a>
            </div>
          }
        }

        @if (feed.loading()) {
          <div class="spinner" aria-label="Loading posts"></div>
        } @else if (feed.failed()) {
          <div class="card empty">Couldn’t load posts. <button class="link-btn" (click)="feed.loadMore()">Try again</button></div>
        } @else if (!feed.done()) {
          <div appInfiniteScroll (reached)="feed.loadMore()"></div>
        } @else if (feed.items().length) {
          <p class="faint end">You’re all caught up ✈</p>
        }
      </section>

      <aside class="side sticky stack">
        @if (requests().length) {
          <div class="card">
            <h3 class="card-title">Friend requests</h3>
            @for (r of requests().slice(0, 3); track r.id) {
              <a class="person" [routerLink]="['/u', r.sender.username]">
                <app-avatar [user]="r.sender" [size]="36" />
                <span class="person-main"><span class="person-name">{{ r.sender.full_name }}</span></span>
              </a>
            }
            <a routerLink="/friends" class="faint">See all requests →</a>
          </div>
        }
        <div class="card">
          <h3 class="card-title">Trending in the crew room</h3>
          @for (t of trends(); track t.term; let i = $index) {
            <div class="trend"><span class="rank">{{ i + 1 }}</span><span class="term">{{ t.term }}</span></div>
          } @empty {
            <p class="faint">Nothing trending yet.</p>
          }
        </div>
      </aside>
    </div>
  `,
  styles: `
    .me { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 8px; }
    .me .name { font: 600 1.1rem var(--font-display); color: var(--ink); }
    .stats { display: flex; justify-content: space-around; width: 100%; margin: 10px 0 0; padding-top: 12px; border-top: 1px solid var(--line); }
    .stats div { display: flex; flex-direction: column-reverse; }
    .stats dt { font-size: 0.75rem; color: var(--ink-faint); }
    .stats dd { margin: 0; font: 600 1.1rem var(--font-display); }
    .trend { display: flex; gap: 10px; padding: 6px 0; align-items: baseline; }
    .rank { font: 600 0.8rem var(--font-display); color: var(--amber); width: 16px; }
    .term { font-weight: 500; text-transform: capitalize; }
    .end { text-align: center; }
    a.person:hover { text-decoration: none; background: var(--surface-2); }
  `,
})
export class FeedPage {
  private api = inject(Api);
  protected auth = inject(AuthService);

  protected feed = new CursorList<Post>((c) => this.api.feed(c));
  protected trends = signal<Trend[]>([]);
  protected requests = signal<FriendRequest[]>([]);
  protected profile = signal<Profile | null>(null);

  constructor() {
    this.feed.loadMore();
    this.api.trends().subscribe({ next: (r) => this.trends.set(r.data), error: () => {} });
    this.api.friendRequests().subscribe({ next: (r) => this.requests.set(r.data), error: () => {} });
    const me = this.auth.user();
    if (me) this.api.profile(me.username).subscribe({ next: (r) => this.profile.set(r.data), error: () => {} });
  }
}
