import { Injectable, computed, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Api } from './api.service';
import { DemoInfo } from './models';

/** Whether this server is the public demo, loaded once on startup. */
@Injectable({ providedIn: 'root' })
export class DemoService {
  private api = inject(Api);
  readonly info = signal<DemoInfo>({ enabled: false, accounts: [] });
  readonly enabled = computed(() => this.info().enabled);

  /** e.g. "4:00 AM Eastern". */
  readonly resetLabel = computed(() => {
    const { reset_at, time_zone } = this.info();
    if (!reset_at) return 'nightly';
    const [h, m] = reset_at.split(':').map(Number);
    const time = new Date(2000, 0, 1, h, m || 0).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    const zone = time_zone === 'America/New_York' ? ' Eastern' : time_zone ? ` (${time_zone})` : '';
    return `every night at ${time}${zone}`;
  });

  async load(): Promise<void> {
    try {
      this.info.set(await firstValueFrom(this.api.demo()));
    } catch {
      // Not a demo server (or the API is down): carry on as normal.
    }
  }
}
