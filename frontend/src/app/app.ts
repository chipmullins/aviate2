import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ToastService } from './core/toast.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: `
    <router-outlet />
    <div class="toasts" aria-live="polite">
      @for (t of toast.toasts(); track t.id) {
        <div class="toast" [class.toast-error]="t.kind === 'error'" [attr.role]="t.kind === 'error' ? 'alert' : 'status'">{{ t.text }}</div>
      }
    </div>
  `,
})
export class App {
  protected toast = inject(ToastService);
}
