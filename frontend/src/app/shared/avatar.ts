import { Component, computed, input } from '@angular/core';
import { User } from '../core/models';

const COLORS = ['#2563eb', '#0f766e', '#7c3aed', '#c2410c', '#0369a1', '#be185d', '#4d7c0f', '#b45309'];

/** Profile photo, or the person's initials on a colored circle when they haven't uploaded one. */
@Component({
  selector: 'app-avatar',
  template: `
    @if (user()?.avatar_url; as url) {
      <img [src]="url" [alt]="''" [style.width.px]="size()" [style.height.px]="size()" class="avatar" />
    } @else {
      <span
        class="avatar initials"
        aria-hidden="true"
        [style.width.px]="size()"
        [style.height.px]="size()"
        [style.font-size.px]="size() * 0.38"
        [style.background]="color()"
        >{{ initials() }}</span
      >
    }
  `,
  styles: `
    :host { display: inline-flex; flex-shrink: 0; }
    .avatar { border-radius: 50%; object-fit: cover; }
    .initials {
      display: inline-flex; align-items: center; justify-content: center;
      color: #fff; font-family: var(--font-display); font-weight: 600; letter-spacing: 0.02em;
    }
  `,
})
export class Avatar {
  readonly user = input<Pick<User, 'id' | 'first_name' | 'last_name' | 'avatar_url'> | null>(null);
  readonly size = input(40);

  protected initials = computed(() => {
    const u = this.user();
    return u ? `${u.first_name.charAt(0)}${u.last_name.charAt(0)}`.toUpperCase() : '?';
  });
  protected color = computed(() => COLORS[(this.user()?.id ?? 0) % COLORS.length]);
}
