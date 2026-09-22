import { Component, effect, input, signal } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { type DashboardSlide, type DashboardSliderSettings } from '../../core/dashboard/dashboard-slides.service';

@Component({
  selector: 'app-dashboard-slider',
  imports: [NgIcon],
  templateUrl: './dashboard-slider.component.html',
  styleUrl: './dashboard-slider.component.css',
})
export class DashboardSliderComponent {
  readonly slides = input<readonly DashboardSlide[]>([]);
  readonly settings = input<DashboardSliderSettings>({ transition: 'slide', animationDurationMs: 400, delayMs: 6000, autoplay: true });
  protected readonly activeIndex = signal(0);
  protected readonly preview = signal<DashboardSlide | null>(null);

  constructor() {
    effect((onCleanup) => {
      if (this.slides().length < 2 || !this.settings().autoplay) return;
      const timer = setInterval(() => this.next(), this.settings().delayMs);
      onCleanup(() => clearInterval(timer));
    });
  }
  protected next(): void { this.show(this.activeIndex() + 1); }
  protected previous(): void { this.show(this.activeIndex() - 1); }

  protected show(index: number): void {
    const count = this.slides().length;
    if (count === 0) return;
    const nextIndex = (index + count) % count;
    if (nextIndex === this.activeIndex()) return;

    this.activeIndex.set(nextIndex);
  }

  protected trackTransform(): string { return `translate3d(-${this.activeIndex() * 100}%, 0, 0)`; }
  protected animationDuration(): string { return `${this.settings().animationDurationMs}ms`; }
  protected open(slide: DashboardSlide): void { if (slide.linkUrl === null) this.preview.set(slide); }
  protected closePreview(): void { this.preview.set(null); }
}
