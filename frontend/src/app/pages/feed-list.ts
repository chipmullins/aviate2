import { Observable } from 'rxjs';
import { signal } from '@angular/core';
import { CursorPage } from '../core/models';

/** Loads a cursor-paginated list page by page. Shared by the feed and profile timelines. */
export class CursorList<T extends { id: number | string }> {
  readonly items = signal<T[]>([]);
  readonly loading = signal(false);
  readonly done = signal(false);
  readonly failed = signal(false);
  private cursor: string | null = null;

  constructor(private fetch: (cursor: string | null) => Observable<CursorPage<T>>) {}

  reset(fetch?: (cursor: string | null) => Observable<CursorPage<T>>): void {
    if (fetch) this.fetch = fetch;
    this.items.set([]);
    this.cursor = null;
    this.done.set(false);
    this.failed.set(false);
    this.loadMore();
  }

  loadMore(): void {
    if (this.loading() || this.done()) return;
    this.loading.set(true);
    this.fetch(this.cursor).subscribe({
      next: (page) => {
        this.items.update((list) => [...list, ...page.data]);
        this.cursor = page.meta.next_cursor;
        this.done.set(!page.meta.next_cursor);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.failed.set(true);
      },
    });
  }

  prepend(item: T): void {
    this.items.update((list) => [item, ...list]);
  }

  remove(id: T['id']): void {
    this.items.update((list) => list.filter((x) => x.id !== id));
  }
}
