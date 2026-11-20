import { DestroyRef, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { Api } from '../core/api.service';
import { CountsService } from '../core/counts.service';
import { AppNotification, NotificationKind } from '../core/models';
import { RealtimeService } from '../core/realtime.service';
import { Avatar } from '../shared/avatar';
import { TimeAgoPipe } from '../shared/time-ago.pipe';

const ICONS: Record<NotificationKind, string> = {
  post_liked: '♥',
  post_commented: '💬',
  wall_post_commented: '💬',
  wall_post_created: '📝',
  friend_request_received: '👋',
  friend_request_accepted: '🤝',
};

@Component({
  selector: 'app-notifications-page',
  imports: [Avatar, TimeAgoPipe],
  template: `
    <div class="page narrow">
      <div class="card">
        <div class="head">
          <h2>Notifications</h2>
          @if (hasUnread()) {
            <button class="btn btn-ghost btn-sm" type="button" (click)="markAll()">Mark all as read</button>
          }
        </div>

        @for (n of items(); track n.id) {
          <button class="note" type="button" [class.unread]="!n.read_at" (click)="open(n)">
            <span class="who">
              <app-avatar [user]="n.actor" [size]="44" />
              <span class="icon" aria-hidden="true">{{ icon(n.kind) }}</span>
            </span>
            <span class="text">
              {{ n.text }}
              <span class="faint">{{ n.created_at | timeAgo }}</span>
            </span>
            @if (!n.read_at) { <span class="dot" aria-label="Unread"></span> }
          </button>
        } @empty {
          @if (!loading()) {
            <div class="empty">
              <div class="empty-icon" aria-hidden="true">🔔</div>
              Nothing yet. Likes, comments and friend requests will show up here.
            </div>
          }
        }

        @if (loading()) {
          <div class="spinner" aria-label="Loading notifications"></div>
        } @else if (page() < lastPage()) {
          <button class="link-btn more" type="button" (click)="load(page() + 1)">Show older</button>
        }
      </div>
    </div>
  `,
  styles: `
    .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .note {
      display: flex; align-items: center; gap: 14px; width: 100%; text-align: left;
      border: none; background: none; font: inherit; color: var(--ink);
      padding: 12px; border-radius: var(--radius-sm); cursor: pointer; margin-bottom: 4px;
    }
    .note:hover { background: var(--surface-2); }
    .note.unread { background: var(--sky-soft); }
    .who { position: relative; }
    .icon {
      position: absolute; right: -4px; bottom: -4px; width: 22px; height: 22px; border-radius: 50%;
      background: #fff; box-shadow: var(--shadow); display: flex; align-items: center; justify-content: center; font-size: 0.7rem;
    }
    .text { flex: 1; display: flex; flex-direction: column; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--sky); }
    .more { display: block; margin: 12px auto 0; }
  `,
})
export class NotificationsPage {
  private api = inject(Api);
  private counts = inject(CountsService);
  private router = inject(Router);

  protected items = signal<AppNotification[]>([]);
  protected loading = signal(false);
  protected page = signal(1);
  protected lastPage = signal(1);

  constructor() {
    this.load(1);
    // New ones arrive live at the top.
    inject(RealtimeService)
      .notifications$.pipe(takeUntilDestroyed(inject(DestroyRef)))
      .subscribe((n) => this.items.update((list) => [n, ...list.filter((x) => x.id !== n.id)]));
  }

  protected hasUnread(): boolean {
    return this.items().some((n) => !n.read_at);
  }

  protected icon(kind: NotificationKind): string {
    return ICONS[kind] ?? '•';
  }

  protected load(page: number): void {
    this.loading.set(true);
    this.api.notifications(page).subscribe({
      next: (r) => {
        this.items.update((list) => (page === 1 ? r.data : [...list, ...r.data]));
        this.page.set(r.meta.current_page);
        this.lastPage.set(r.meta.last_page);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected markAll(): void {
    this.api.markAllNotificationsRead().subscribe(() => {
      const now = new Date().toISOString();
      this.items.update((list) => list.map((n) => ({ ...n, read_at: n.read_at ?? now })));
      this.counts.refresh();
    });
  }

  protected open(n: AppNotification): void {
    if (!n.read_at) {
      this.api.markNotificationRead(n.id).subscribe({ next: () => this.counts.refresh(), error: () => {} });
    }
    if (n.post_id) this.router.navigate(['/posts', n.post_id]);
    else this.router.navigate(n.kind === 'friend_request_received' ? ['/friends'] : ['/u', n.actor.username]);
  }
}
