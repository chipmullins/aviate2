import { Injectable, effect, inject } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { Subject, firstValueFrom } from 'rxjs';
import { Api } from './api.service';
import { AuthService } from './auth.service';
import { AppNotification, Message, User } from './models';

export interface IncomingMessage {
  message: Message;
  sender: User;
}

/**
 * Live updates over WebSockets (Laravel Reverb, which speaks the Pusher protocol).
 * Connects when someone signs in and joins only their own private channel;
 * the server checks that at /api/broadcasting/auth.
 */
@Injectable({ providedIn: 'root' })
export class RealtimeService {
  private api = inject(Api);
  private auth = inject(AuthService);
  private echo: Echo<'reverb'> | null = null;
  private channelName: string | null = null;

  readonly messages$ = new Subject<IncomingMessage>();
  readonly notifications$ = new Subject<AppNotification>();

  constructor() {
    effect(() => {
      const user = this.auth.user();
      if (user) void this.connect(user.id);
      else this.disconnect();
    });
  }

  private async connect(userId: number): Promise<void> {
    if (this.echo) return;

    const { key } = await firstValueFrom(this.api.realtimeConfig()).catch(() => ({ key: null }));
    if (!key) return; // Real-time isn't configured; the app still works, just without live updates.

    const secure = location.protocol === 'https:';
    const port = Number(location.port) || (secure ? 443 : 80);

    this.echo = new Echo({
      broadcaster: 'reverb',
      Pusher,
      key,
      // nginx forwards /app on the same host and port to Reverb, so no extra ports or CORS.
      wsHost: location.hostname,
      wsPort: port,
      wssPort: port,
      forceTLS: secure,
      enabledTransports: ['ws', 'wss'],
      // Use Angular's HttpClient so the session cookie and CSRF header go along.
      authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: Error | null, data: { auth: string } | null) => void) => {
          this.api.broadcastingAuth(socketId, channel.name).subscribe({
            next: (data) => callback(null, data),
            error: (err) => callback(err instanceof Error ? err : new Error('Channel authorization failed'), null),
          });
        },
      }),
    } as never);

    this.channelName = `App.Models.User.${userId}`;
    this.echo
      .private(this.channelName)
      .listen('.message.sent', (e: IncomingMessage) => this.messages$.next(e))
      .notification((n: AppNotification) => this.notifications$.next(n));
  }

  private disconnect(): void {
    if (!this.echo) return;
    if (this.channelName) this.echo.leave(this.channelName);
    this.echo.disconnect();
    this.echo = null;
    this.channelName = null;
  }
}
