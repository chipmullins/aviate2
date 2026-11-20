import { DatePipe } from '@angular/common';
import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { AuthService, errorMessage } from '../core/auth.service';
import { CountsService } from '../core/counts.service';
import { FriendshipStatus, Post, Profile, User } from '../core/models';
import { ToastService } from '../core/toast.service';
import { Avatar } from '../shared/avatar';
import { InfiniteScroll } from '../shared/infinite-scroll.directive';
import { PostCard } from '../shared/post-card.component';
import { PostComposer } from '../shared/post-composer.component';
import { CursorList } from './feed-list';

@Component({
  selector: 'app-profile-page',
  imports: [Avatar, PostCard, PostComposer, RouterLink, InfiniteScroll, DatePipe],
  template: `
    @if (notFound()) {
      <div class="page narrow">
        <div class="card empty">
          <div class="empty-icon" aria-hidden="true">🧭</div>
          <h2>Profile not found</h2>
          <p>This account doesn’t exist or has been closed.</p>
          <a class="btn" routerLink="/">Back to feed</a>
        </div>
      </div>
    } @else if (profile(); as p) {
      <div class="cover"></div>
      <div class="page head-wrap">
        <div class="card header">
          <app-avatar class="big" [user]="p" [size]="128" />
          <div class="info">
            <h1>{{ p.full_name }}</h1>
            <div class="meta">
              @if (p.airline) { <span class="pill">{{ p.airline }}</span> }
              <span class="faint">&#64;{{ p.username }} · Joined {{ p.joined_at | date: 'MMMM y' }}</span>
            </div>
            <dl class="stats">
              <div><dd>{{ p.posts_count }}</dd><dt>Posts</dt></div>
              <div><dd>{{ p.friends_count }}</dd><dt>Friends</dt></div>
              <div><dd>{{ p.likes_received }}</dd><dt>Likes</dt></div>
              @if (!isMe() && p.mutual_friends_count) {
                <div><dd>{{ p.mutual_friends_count }}</dd><dt>Mutual</dt></div>
              }
            </dl>
          </div>

          <div class="actions">
            @switch (status()) {
              @case ('self') {
                <a class="btn btn-ghost" routerLink="/settings">Edit profile</a>
              }
              @case ('friends') {
                <a class="btn" [routerLink]="['/messages', p.username]">Message</a>
                <button class="btn btn-ghost" type="button" (click)="act('unfriend')" [disabled]="busy()">Unfriend</button>
              }
              @case ('request_sent') {
                <button class="btn btn-ghost" type="button" (click)="act('cancel')" [disabled]="busy()">Cancel request</button>
              }
              @case ('request_received') {
                <button class="btn" type="button" (click)="act('accept')" [disabled]="busy()">Accept request</button>
                <button class="btn btn-ghost" type="button" (click)="act('cancel')" [disabled]="busy()">Decline</button>
              }
              @default {
                <button class="btn" type="button" (click)="act('send')" [disabled]="busy()">Add friend</button>
              }
            }
          </div>
        </div>

        <nav class="tabs" aria-label="Profile sections">
          <button type="button" [class.on]="tab() === 'posts'" (click)="tab.set('posts')">Posts</button>
          <button type="button" [class.on]="tab() === 'friends'" (click)="showFriends()">Friends · {{ p.friends_count }}</button>
        </nav>

        <div class="narrow stack">
          @if (tab() === 'posts') {
            @if (canPost()) {
              <app-post-composer
                [wall]="isMe() ? null : p.username"
                [placeholder]="isMe() ? 'Share something with your crew…' : 'Write something to ' + p.first_name + '…'"
                (created)="posts.prepend($event)"
              />
            }
            @for (post of posts.items(); track post.id) {
              <app-post-card [post]="post" (deleted)="posts.remove($event)" />
            } @empty {
              @if (!posts.loading()) {
                <div class="card empty">No posts yet.</div>
              }
            }
            @if (posts.loading()) {
              <div class="spinner" aria-label="Loading posts"></div>
            } @else if (!posts.done() && !posts.failed()) {
              <div appInfiniteScroll (reached)="posts.loadMore()"></div>
            }
          } @else {
            <div class="card">
              @for (f of friends(); track f.id) {
                <a class="person" [routerLink]="['/u', f.username]">
                  <app-avatar [user]="f" [size]="44" />
                  <span class="person-main">
                    <span class="person-name">{{ f.full_name }}</span>
                    <span class="faint">{{ f.airline }}</span>
                  </span>
                </a>
              } @empty {
                <p class="muted">No friends to show yet.</p>
              }
            </div>
          }
        </div>
      </div>
    } @else {
      <div class="spinner" aria-label="Loading profile"></div>
    }
  `,
  styles: `
    .cover { height: 180px; background: linear-gradient(120deg, #0b1b3a, #1e3f7a 60%, #f5a524 140%); }
    .head-wrap { margin-top: -90px; padding-top: 0; }
    .header { display: flex; align-items: flex-end; gap: 22px; flex-wrap: wrap; padding: 22px; }
    .big { border: 5px solid #fff; border-radius: 50%; margin-top: -60px; box-shadow: var(--shadow); }
    .info { flex: 1; min-width: 220px; }
    h1 { margin-bottom: 6px; }
    .meta { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .stats { display: flex; gap: 24px; margin: 14px 0 0; }
    .stats div { display: flex; flex-direction: column; }
    .stats dd { margin: 0; font: 600 1.15rem var(--font-display); }
    .stats dt { font-size: 0.78rem; color: var(--ink-faint); }
    .actions { display: flex; gap: 8px; }
    .tabs { display: flex; gap: 4px; justify-content: center; margin: 18px 0; }
    .tabs button {
      border: none; background: none; padding: 9px 18px; border-radius: 999px; cursor: pointer;
      font: 500 0.95rem var(--font-display); color: var(--ink-soft);
    }
    .tabs button.on { background: var(--navy); color: #fff; }
    a.person:hover { text-decoration: none; background: var(--surface-2); }
  `,
})
export class ProfilePage {
  private api = inject(Api);
  private auth = inject(AuthService);
  private counts = inject(CountsService);
  private toast = inject(ToastService);

  /** From the route: /u/:username */
  readonly username = input.required<string>();

  protected profile = signal<Profile | null>(null);
  protected notFound = signal(false);
  protected status = signal<FriendshipStatus>('none');
  protected busy = signal(false);
  protected tab = signal<'posts' | 'friends'>('posts');
  protected friends = signal<User[]>([]);
  protected posts = new CursorList<Post>(() => this.api.userPosts(this.username()));

  protected isMe = computed(() => this.auth.user()?.username === this.profile()?.username);
  protected canPost = computed(() => this.isMe() || this.status() === 'friends');

  constructor() {
    effect(() => {
      const username = this.username();
      untracked(() => this.load(username));
    });
  }

  private load(username: string): void {
    this.profile.set(null);
    this.notFound.set(false);
    this.tab.set('posts');
    this.friends.set([]);
    this.api.profile(username).subscribe({
      next: ({ data }) => {
        this.profile.set(data);
        this.status.set(data.friendship_status);
        this.posts.reset();
      },
      error: () => this.notFound.set(true),
    });
  }

  protected showFriends(): void {
    this.tab.set('friends');
    if (!this.friends().length) {
      this.api.friendsOf(this.username()).subscribe({ next: (r) => this.friends.set(r.data), error: () => {} });
    }
  }

  protected act(action: 'send' | 'accept' | 'cancel' | 'unfriend'): void {
    const u = this.username();
    const call = {
      send: () => this.api.sendFriendRequest(u),
      accept: () => this.api.acceptFriendRequest(u),
      cancel: () => this.api.cancelFriendRequest(u),
      unfriend: () => this.api.unfriend(u),
    }[action];

    this.busy.set(true);
    call().subscribe({
      next: (r) => {
        const wasFriends = this.status() === 'friends';
        this.status.set(r.friendship_status);
        this.busy.set(false);
        this.counts.refresh();
        // Keep the friend count in step without reloading the page.
        const nowFriends = r.friendship_status === 'friends';
        if (wasFriends !== nowFriends) {
          this.profile.update((p) => (p ? { ...p, friends_count: p.friends_count + (nowFriends ? 1 : -1) } : p));
        }
        if (action === 'send' && r.friendship_status === 'request_sent') this.toast.show('Friend request sent');
      },
      error: (err) => {
        this.busy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
