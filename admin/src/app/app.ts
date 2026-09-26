import { Component, DestroyRef, ElementRef, afterNextRender, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { observeErrorMessages } from './shared/error-scroll';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    afterNextRender(() => this.destroyRef.onDestroy(observeErrorMessages(this.host.nativeElement)));
  }
}
