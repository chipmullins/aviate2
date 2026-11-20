import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api.service';
import { errorMessage } from '../core/auth.service';
import { Comment, Post } from '../core/models';
import { ToastService } from '../core/toast.service';
import { Avatar } from './avatar';
import { PostBody } from './post-body.component';
import { TimeAgoPipe } from './time-ago.pipe';

@Component({
  selector: 'app-post-card',
  imports: [Avatar, PostBody, RouterLink, TimeAgoPipe, FormsModule],
  templateUrl: './post-card.component.html',
  styleUrl: './post-card.component.scss',
})
export class PostCard {
  private api = inject(Api);
  private toast = inject(ToastService);

  readonly post = input.required<Post>();
  /** Start with comments open (used on the single-post page). */
  readonly expanded = input(false);
  readonly deleted = output<number>();

  protected liked = signal<boolean | null>(null);
  protected likes = signal<number | null>(null);
  protected likeBusy = signal(false);

  protected showComments = signal(false);
  protected comments = signal<Comment[]>([]);
  protected commentsCursor = signal<string | null>(null);
  protected commentsLoading = signal(false);
  protected commentCount = signal<number | null>(null);
  protected draft = signal('');
  protected sending = signal(false);
  protected confirmingDelete = signal(false);

  ngOnInit(): void {
    if (this.expanded()) this.toggleComments();
  }

  protected isLiked(): boolean {
    return this.liked() ?? this.post().liked_by_me;
  }
  protected likeCount(): number {
    return this.likes() ?? this.post().likes_count;
  }
  protected totalComments(): number {
    return this.commentCount() ?? this.post().comments_count;
  }

  protected toggleLike(): void {
    if (this.likeBusy()) return;
    const wasLiked = this.isLiked();
    // Optimistic: update right away, then settle on what the server says.
    this.liked.set(!wasLiked);
    this.likes.set(this.likeCount() + (wasLiked ? -1 : 1));
    this.likeBusy.set(true);

    const req = wasLiked ? this.api.unlike(this.post().id) : this.api.like(this.post().id);
    req.subscribe({
      next: (r) => {
        this.liked.set(r.liked);
        this.likes.set(r.likes_count);
        this.likeBusy.set(false);
      },
      error: (err) => {
        this.liked.set(wasLiked);
        this.likes.set(this.likeCount() + (wasLiked ? 1 : -1));
        this.likeBusy.set(false);
        this.toast.error(errorMessage(err));
      },
    });
  }

  protected toggleComments(): void {
    this.showComments.update((v) => !v);
    if (this.showComments() && this.comments().length === 0) this.loadComments();
  }

  protected loadComments(): void {
    this.commentsLoading.set(true);
    this.api.comments(this.post().id, this.commentsCursor()).subscribe({
      next: (page) => {
        this.comments.update((c) => [...c, ...page.data]);
        this.commentsCursor.set(page.meta.next_cursor);
        this.commentsLoading.set(false);
      },
      error: () => this.commentsLoading.set(false),
    });
  }

  protected addComment(event: Event): void {
    event.preventDefault();
    const body = this.draft().trim();
    if (!body || this.sending()) return;
    this.sending.set(true);
    this.api.addComment(this.post().id, body).subscribe({
      next: ({ data }) => {
        this.comments.update((c) => [...c, data]);
        this.commentCount.set(this.totalComments() + 1);
        this.draft.set('');
        this.sending.set(false);
      },
      error: (err) => {
        this.sending.set(false);
        this.toast.error(errorMessage(err, 'Your comment couldn’t be posted.'));
      },
    });
  }

  protected removeComment(comment: Comment): void {
    this.api.deleteComment(comment.id).subscribe({
      next: () => {
        this.comments.update((c) => c.filter((x) => x.id !== comment.id));
        this.commentCount.set(Math.max(0, this.totalComments() - 1));
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }

  protected remove(): void {
    this.api.deletePost(this.post().id).subscribe({
      next: () => {
        this.toast.show('Post deleted');
        this.deleted.emit(this.post().id);
      },
      error: (err) => this.toast.error(errorMessage(err)),
    });
  }
}
