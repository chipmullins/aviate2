import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { DemoService } from './demo.service';

describe('DemoService', () => {
  let demo: DemoService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    demo = TestBed.inject(DemoService);
    http = TestBed.inject(HttpTestingController);
  });

  it('turns on with the reset time when the server is a demo', async () => {
    const done = demo.load();
    http.expectOne('/api/demo').flush({ enabled: true, reset_at: '04:00', time_zone: 'America/New_York', accounts: [] });
    await done;
    expect(demo.enabled()).toBe(true);
    expect(demo.resetLabel()).toBe('every night at 4:00 AM Eastern');
  });

  it('stays off if the check fails', async () => {
    const done = demo.load();
    http.expectOne('/api/demo').flush(null, { status: 500, statusText: 'Server Error' });
    await done;
    expect(demo.enabled()).toBe(false);
  });
});
