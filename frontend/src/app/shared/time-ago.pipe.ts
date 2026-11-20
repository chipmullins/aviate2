import { Pipe, PipeTransform } from '@angular/core';

/** "just now", "5m", "3h", "2d", then a date. */
@Pipe({ name: 'timeAgo' })
export class TimeAgoPipe implements PipeTransform {
  transform(iso: string | null | undefined): string {
    if (!iso) return '';
    const then = new Date(iso);
    const seconds = Math.round((Date.now() - then.getTime()) / 1000);
    if (seconds < 45) return 'just now';
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.round(hours / 24);
    if (days < 7) return `${days}d`;
    return then.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: then.getFullYear() === new Date().getFullYear() ? undefined : 'numeric',
    });
  }
}
