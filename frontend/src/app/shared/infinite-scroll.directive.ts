import { Directive, ElementRef, OnDestroy, OnInit, inject, output } from '@angular/core';

/** Fires (reached) when this element scrolls into view. Put it after the last item of a list. */
@Directive({ selector: '[appInfiniteScroll]' })
export class InfiniteScroll implements OnInit, OnDestroy {
  private el = inject(ElementRef<HTMLElement>);
  private observer?: IntersectionObserver;
  readonly reached = output<void>();

  ngOnInit(): void {
    this.observer = new IntersectionObserver((entries) => {
      if (entries.some((e) => e.isIntersecting)) this.reached.emit();
    }, { rootMargin: '400px' });
    this.observer.observe(this.el.nativeElement);
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }
}
