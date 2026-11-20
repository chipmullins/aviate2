import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { AuthService, errorMessage } from '../core/auth.service';
import { CountsService } from '../core/counts.service';
import { FriendRequest, User } from '../core/models';
import { ToastService } from '../core/toast.service';
import { Avatar } from '../shared/avatar';
import { TimeAgoPipe } from '../shared/time-ago.pipe';

@Component({
  selector: 'app-friends-page',
  imports: [Avatar, RouterLink, TimeAgoPipe],
  template: `
    <div class="page grid-2">
      <section class="card" aria-labelledby="req-title">
        <h2 id="req-title">Friend requests</h2>
        @for (r of requests(); track r.id) {
          <div class="person">
            <a [routerLink]="['/u', r.sender.username]"><app-avatar [user]="r.sender" [size]="44" /></a>
            <div class="person-main">
              <a class="person-name" [routerLink]="['/u', r.sender.username]">{{ r.sender.full_name }}</a>
              <span class="faint">{{ r.sender.airline }} · {{ r.created_at | timeAgo }}</span>
            </div>
            <div class="person-actions">
              <button class="btn btn-sm" type="button" (click)="answer(r, true)">Accept</button>
              <button class="btn btn-ghost btn-sm" type="button" (click)="answer(r, false)">Decline</button>
            </div>
          </div>
        } @empty {
          <p class="muted">No pending requests.</p>
        }
      </section>

      <section class="card" aria-labelledby="friends-title">
        <h2 id="friends-title">Your friends</h2>
        @for (f of friends(); track f.id) {
          <div class="person">
            <a [routerLink]="['/u', f.username]"><app-avatar [user]="f" [size]="44" /></a>
            <div class="person-main">
              <a class="person-name" [routerLink]="['/u', f.username]">{{ f.full_name }}</a>
              <span class="faint">{{ f.airline }}</span>
            </div>
            <div class="person-actions">
              <a class="btn btn-ghost btn-sm" [routerLink]="['/messages', f.username]">Message</a>
            </div>
          </div>
        } @empty {
          <div class="empty">
            <p>You haven’t added anyone yet.</p>
            <a class="btn" routerLink="/search">Find crew</a>
          </div>
        }
      </section>
    </div>
  `,
})
export class FriendsPage {
  private api = inject(Api);
  private auth = inject(AuthService);
  private counts = inject(CountsService);
  private toast = inject(ToastService);

  protected requests = signal<FriendRequest[]>([]);
  protected friends = signal<User[]>([]);

  constructor() {
    this.api.friendRequests().subscribe({ next: (r) => this.requests.set(r.data), error: () => {} });
    this.loadFriends();
  }

  private loadFriends(): void {
    const me = this.auth.user();
    if (me) this.api.friendsOf(me.username).subscribe({ next: (r) => this.friends.set(r.data), error: () => {} });
  }

  protected answer(r: FriendRequest, accept: boolean): void {
    const call = accept ? this.api.acceptFriendRequest(r.sender.username) : this.api.cancelFriendRequest(r.sender.username);
    call.subscribe({
      next: () => {
        this.requests.update((list) => list.filter((x) => x.id !== r.id));
        this.counts.refresh();
        if (accept) {
          this.toast.show(`You and ${r.sender.first_name} are now friends`);
          this.loadFriends();
        }
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }
}
