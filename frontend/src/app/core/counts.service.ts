import { Injectable, effect, inject, signal } from '@angular/core';
import { Api } from './api.service';
import { AuthService } from './auth.service';
import { Counts } from './models';
import { RealtimeService } from './realtime.service';

const ZERO: Counts = { friend_requests: 0, messages: 0, notifications: 0 };

/** The numbers on the top-bar badges. Loaded on sign-in, then kept fresh by live events. */
@Injectable({ providedIn: 'root' })
export class CountsService {
  private api = inject(Api);
  private auth = inject(AuthService);
  private realtime = inject(RealtimeService);

  readonly counts = signal<Counts>(ZERO);

  constructor() {
    effect(() => {
      if (this.auth.user()) this.refresh();
      else this.counts.set(ZERO);
    });

    // Any live event can change more than one badge (e.g. a friend request is also a notification),
    // so just ask the server for the true numbers.
    this.realtime.messages$.subscribe(() => this.refresh());
    this.realtime.notifications$.subscribe(() => this.refresh());
  }

  refresh(): void {
    this.api.counts().subscribe({ next: (c) => this.counts.set(c), error: () => {} });
  }
}
