import { Component, computed, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api } from '../core/api.service';
import { AuthService, errorMessage } from '../core/auth.service';
import { Post } from '../core/models';
import { Avatar } from './avatar';

const MAX_BYTES = 5 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

@Component({
  selector: 'app-post-composer',
  imports: [FormsModule, Avatar],
  template: `
    <form class="card composer" (submit)="submit($event)">
      <div class="top">
        <app-avatar [user]="auth.user()" [size]="42" />
        <label class="sr-only" for="composer-text">{{ placeholder() }}</label>
        <textarea
          id="composer-text"
          class="textarea"
          [placeholder]="placeholder()"
          [ngModel]="text()"
          (ngModelChange)="text.set($event)"
          name="body"
          maxlength="5000"
          rows="2"
        ></textarea>
      </div>

      @if (previewUrl(); as url) {
        <div class="preview">
          <img [src]="url" alt="Selected photo" />
          <button type="button" class="btn btn-quiet btn-sm remove" (click)="clearImage()" aria-label="Remove photo">✕</button>
        </div>
      }
      @if (error()) {
        <div class="alert alert-error" role="alert">{{ error() }}</div>
      }

      <div class="bottom">
        <label class="btn btn-quiet btn-sm photo">
          <span aria-hidden="true">📷</span> Photo
          <input type="file" accept="image/jpeg,image/png,image/webp" (change)="pick($event)" hidden />
        </label>
        <span class="faint">{{ remaining() < 200 ? remaining() + ' left' : '' }}</span>
        <button class="btn" type="submit" [disabled]="!canPost()">{{ busy() ? 'Posting…' : 'Post' }}</button>
      </div>
    </form>
  `,
  styles: `
    .top { display: flex; gap: 12px; align-items: flex-start; }
    .textarea { min-height: 64px; border-color: transparent; background: var(--surface-2); }
    .bottom { display: flex; align-items: center; gap: 12px; margin-top: 12px; }
    .bottom .faint { flex: 1; text-align: right; }
    .photo { cursor: pointer; }
    .preview { position: relative; margin: 12px 0 0 54px; }
    .preview img { max-height: 260px; border-radius: var(--radius-sm); }
    .remove { position: absolute; top: 6px; left: 6px; background: rgba(255,255,255,.9); }
    .alert { margin: 12px 0 0; }
  `,
})
export class PostComposer {
  private api = inject(Api);
  protected auth = inject(AuthService);

  /** Username of the profile being posted on; null for your own feed. */
  readonly wall = input<string | null>(null);
  readonly placeholder = input('Share something with your crew…');
  readonly created = output<Post>();

  protected text = signal('');
  protected image = signal<File | null>(null);
  protected previewUrl = signal<string | null>(null);
  protected busy = signal(false);
  protected error = signal<string | null>(null);

  protected remaining = computed(() => 5000 - this.text().length);
  protected canPost = computed(() => !this.busy() && (this.text().trim().length > 0 || this.image() !== null));

  protected pick(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    if (!TYPES.includes(file.type)) return this.error.set('Photos must be JPG, PNG or WebP.');
    if (file.size > MAX_BYTES) return this.error.set('Photos must be 5 MB or smaller.');
    this.error.set(null);
    this.clearImage();
    this.image.set(file);
    this.previewUrl.set(URL.createObjectURL(file));
  }

  protected clearImage(): void {
    const url = this.previewUrl();
    if (url) URL.revokeObjectURL(url);
    this.previewUrl.set(null);
    this.image.set(null);
  }

  protected submit(event: Event): void {
    event.preventDefault();
    if (!this.canPost()) return;
    this.busy.set(true);
    this.error.set(null);

    this.api.createPost(this.text().trim(), this.image(), this.wall()).subscribe({
      next: ({ data }) => {
        this.text.set('');
        this.clearImage();
        this.busy.set(false);
        this.created.emit(data);
      },
      error: (err) => {
        this.busy.set(false);
        this.error.set(errorMessage(err, 'Your post couldn’t be published.'));
      },
    });
  }
}
