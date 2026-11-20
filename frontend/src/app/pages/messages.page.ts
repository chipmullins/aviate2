import { DestroyRef, Component, ElementRef, computed, effect, inject, input, signal, untracked, viewChild } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { AuthService, errorMessage } from '../core/auth.service';
import { CountsService } from '../core/counts.service';
import { Conversation, Message, Profile, User } from '../core/models';
import { RealtimeService } from '../core/realtime.service';
import { ToastService } from '../core/toast.service';
import { Avatar } from '../shared/avatar';
import { TimeAgoPipe } from '../shared/time-ago.pipe';

@Component({
  selector: 'app-messages-page',
  imports: [Avatar, RouterLink, FormsModule, TimeAgoPipe],
  templateUrl: './messages.page.html',
  styleUrl: './messages.page.scss',
})
export class MessagesPage {
  private api = inject(Api);
  private auth = inject(AuthService);
  private counts = inject(CountsService);
  private realtime = inject(RealtimeService);
  private toast = inject(ToastService);
  private router = inject(Router);
  private destroyRef = inject(DestroyRef);

  /** From the route: /messages/:username (optional). */
  readonly username = input<string | undefined>();

  private scroller = viewChild<ElementRef<HTMLElement>>('scroller');

  protected conversations = signal<Conversation[]>([]);
  protected friends = signal<User[]>([]);
  protected partner = signal<Profile | null>(null);
  protected partnerMissing = signal(false);
  /** Oldest first, for display. */
  protected messages = signal<Message[]>([]);
  protected olderCursor = signal<string | null>(null);
  protected loadingThread = signal(false);
  protected draft = signal('');
  protected sending = signal(false);

  protected meId = computed(() => this.auth.user()?.id ?? 0);
  protected canMessage = computed(() => this.partner()?.friendship_status === 'friends');
  /** Friends you haven't talked to yet, for "start a conversation". */
  protected newPeople = computed(() => {
    const talking = new Set(this.conversations().map((c) => c.user.id));
    return this.friends().filter((f) => !talking.has(f.id));
  });

  constructor() {
    this.loadConversations();
    const me = this.auth.user();
    if (me) this.api.friendsOf(me.username).subscribe({ next: (r) => this.friends.set(r.data), error: () => {} });

    effect(() => {
      const username = this.username();
      untracked(() => this.openThread(username));
    });

    // Live messages: show them in the open thread, and bump the conversation list.
    this.realtime.messages$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(({ message, sender }) => {
      if (this.partner()?.id === sender.id) {
        this.messages.update((m) => (m.some((x) => x.id === message.id) ? m : [...m, message]));
        this.api.markThreadRead(sender.username).subscribe({ next: () => this.counts.refresh(), error: () => {} });
        this.scrollToBottom();
      }
      this.loadConversations();
    });
  }

  private loadConversations(): void {
    this.api.conversations().subscribe({ next: (r) => this.conversations.set(r.data), error: () => {} });
  }

  private openThread(username: string | undefined): void {
    this.partner.set(null);
    this.partnerMissing.set(false);
    this.messages.set([]);
    this.olderCursor.set(null);
    this.draft.set('');
    if (!username) return;

    this.api.profile(username).subscribe({
      next: ({ data }) => {
        if (data.friendship_status === 'self') {
          this.router.navigate(['/messages']);
          return;
        }
        this.partner.set(data);
        this.loadPage(true);
      },
      error: () => this.partnerMissing.set(true),
    });
  }

  protected loadPage(first = false): void {
    const p = this.partner();
    if (!p || this.loadingThread()) return;
    this.loadingThread.set(true);
    this.api.thread(p.username, first ? null : this.olderCursor()).subscribe({
      next: (page) => {
        const older = [...page.data].reverse();
        this.messages.update((m) => [...older, ...m]);
        this.olderCursor.set(page.meta.next_cursor);
        this.loadingThread.set(false);
        if (first) {
          this.scrollToBottom();
          // Opening the thread marked messages read on the server.
          this.counts.refresh();
          this.conversations.update((list) => list.map((c) => (c.user.id === p.id ? { ...c, unread_count: 0 } : c)));
        }
      },
      error: () => this.loadingThread.set(false),
    });
  }

  protected send(event?: Event): void {
    event?.preventDefault();
    const p = this.partner();
    const body = this.draft().trim();
    if (!p || !body || this.sending()) return;
    this.sending.set(true);
    this.api.sendMessage(p.username, body).subscribe({
      next: ({ data }) => {
        this.messages.update((m) => [...m, data]);
        this.draft.set('');
        this.sending.set(false);
        this.scrollToBottom();
        this.loadConversations();
      },
      error: (err) => {
        this.sending.set(false);
        this.toast.error(errorMessage(err, 'Your message couldn’t be sent.'));
      },
    });
  }

  /** Enter sends; Shift+Enter makes a new line. */
  protected onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) this.send(event);
  }

  protected remove(m: Message): void {
    this.api.deleteMessage(m.id).subscribe({
      next: () => {
        this.messages.update((list) => list.filter((x) => x.id !== m.id));
        this.loadConversations();
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }

  /** Show a date divider above the first message of each day. */
  protected newDay(i: number): boolean {
    const list = this.messages();
    if (i === 0) return true;
    return new Date(list[i].created_at).toDateString() !== new Date(list[i - 1].created_at).toDateString();
  }

  protected dayLabel(iso: string): string {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(Date.now() - 86_400_000);
    if (d.toDateString() === today.toDateString()) return 'Today';
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    return d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
  }

  protected clock(iso: string): string {
    return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }

  private scrollToBottom(): void {
    setTimeout(() => {
      const el = this.scroller()?.nativeElement;
      if (el) el.scrollTop = el.scrollHeight;
    });
  }
}
