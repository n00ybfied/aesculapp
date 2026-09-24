import { Component, ElementRef, inject, viewChild } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { PwaInstallService } from '../../core/pwa/pwa-install.service';

@Component({
  selector: 'app-pwa-install-banner',
  imports: [NgIcon],
  templateUrl: './pwa-install-banner.component.html',
})
export class PwaInstallBannerComponent {
  protected readonly install = inject(PwaInstallService);
  private readonly instructionsDialog = viewChild<ElementRef<HTMLDialogElement>>('instructionsDialog');

  protected openInstall(): void {
    if (this.install.platform === 'ios') {
      this.instructionsDialog()?.nativeElement.showModal();
    } else {
      void this.install.promptInstall();
    }
  }

  protected closeInstructions(): void {
    this.instructionsDialog()?.nativeElement.close();
  }
}
