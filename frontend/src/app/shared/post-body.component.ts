import { Component, computed, inject, input } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { parseBody, youtubeId } from './post-body';

@Component({
  selector: 'app-post-body',
  template: `
    @if (text()) {
      <!-- Kept on one line on purpose: the paragraph preserves whitespace, so template indentation would show. -->
      <p class="body">@for (s of segments(); track $index) {@if (s.kind === 'link') {<a [href]="s.href" target="_blank" rel="noopener noreferrer nofollow">{{ s.text }}</a>} @else {{{ s.text }}}}</p>
    }
    @if (embed(); as src) {
      <div class="video">
        <iframe
          [src]="src"
          title="YouTube video"
          loading="lazy"
          referrerpolicy="strict-origin-when-cross-origin"
          allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
        ></iframe>
      </div>
    }
  `,
  styles: `
    .body { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
    .video { position: relative; aspect-ratio: 16 / 9; margin-top: 12px; border-radius: var(--radius-sm); overflow: hidden; background: #000; }
    iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
  `,
})
export class PostBody {
  private sanitizer = inject(DomSanitizer);
  readonly text = input<string | null>(null);

  protected segments = computed(() => parseBody(this.text() ?? ''));

  protected embed = computed<SafeResourceUrl | null>(() => {
    const id = youtubeId(this.text() ?? '');
    // Safe to trust: the URL is built by us from an ID that passed a strict [A-Za-z0-9_-]{11} check.
    return id ? this.sanitizer.bypassSecurityTrustResourceUrl(`https://www.youtube-nocookie.com/embed/${id}`) : null;
  });
}
