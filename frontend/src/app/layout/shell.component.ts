import { Component, ElementRef, HostListener, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged, of, switchMap } from 'rxjs';
import { Api } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { CountsService } from '../core/counts.service';
import { SearchResult } from '../core/models';
import { Avatar } from '../shared/avatar';

@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule, Avatar],
  templateUrl: './shell.component.html',
  styleUrl: './shell.component.scss',
})
export class Shell {
  protected auth = inject(AuthService);
  protected counts = inject(CountsService);
  private api = inject(Api);
  private router = inject(Router);
  private host = inject(ElementRef<HTMLElement>);

  protected query = signal('');
  protected results = signal<SearchResult[]>([]);
  protected searchOpen = signal(false);
  protected menuOpen = signal(false);
  private typed = new Subject<string>();

  constructor() {
    this.typed
      .pipe(
        debounceTime(200),
        distinctUntilChanged(),
        switchMap((q) => (q.trim().length >= 2 ? this.api.search(q.trim(), 6) : of({ data: [] }))),
      )
      .subscribe({ next: (r) => this.results.set(r.data), error: () => this.results.set([]) });
  }

  protected onType(q: string): void {
    this.query.set(q);
    this.searchOpen.set(true);
    this.typed.next(q);
  }

  protected submitSearch(event: Event): void {
    event.preventDefault();
    const q = this.query().trim();
    if (!q) return;
    this.closeSearch();
    this.router.navigate(['/search'], { queryParams: { q } });
  }

  protected closeSearch(): void {
    this.searchOpen.set(false);
  }

  protected openResult(): void {
    this.closeSearch();
    this.query.set('');
    this.results.set([]);
  }

  protected logout(): void {
    this.menuOpen.set(false);
    this.auth.logout();
  }

  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    const target = event.target as Node;
    const el = this.host.nativeElement as HTMLElement;
    if (!el.querySelector('.search')?.contains(target)) this.closeSearch();
    if (!el.querySelector('.me')?.contains(target)) this.menuOpen.set(false);
  }

  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.closeSearch();
    this.menuOpen.set(false);
  }
}
