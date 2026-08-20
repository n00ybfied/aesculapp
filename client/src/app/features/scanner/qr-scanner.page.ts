import { AfterViewInit, Component, ElementRef, OnDestroy, inject, signal, viewChild } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { statusMessages } from '../../core/i18n/status-messages';
import { ReceiptRepository, type ReceiptPreview } from '../../core/receipts/receipt.repository';
import { QrScannerService, type QrScanResult } from '../../core/scanner/qr-scanner.service';

@Component({
  selector: 'app-qr-scanner-page',
  imports: [NgIcon],
  templateUrl: './qr-scanner.page.html',
})
export class QrScannerPage implements AfterViewInit, OnDestroy {
  private readonly qrScanner = inject(QrScannerService);
  private readonly receiptRepository = inject(ReceiptRepository);
  private readonly statusMessages = inject(StatusMessageService);
  private readonly cameraPreview = viewChild.required<ElementRef<HTMLVideoElement>>('cameraPreview');

  protected readonly isScanning = signal(false);
  protected readonly isReadingImage = signal(false);
  protected readonly receiptPreview = signal<ReceiptPreview | null>(null);
  protected readonly isImporting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  async ngAfterViewInit(): Promise<void> {
    await this.startCamera();
  }

  ngOnDestroy(): void {
    this.qrScanner.stop();
  }

  protected async retryCamera(): Promise<void> {
    await this.startCamera();
  }

  protected async selectImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.item(0);
    input.value = '';

    if (!file || this.receiptPreview()) {
      return;
    }

    this.qrScanner.stop();
    this.isScanning.set(false);
    this.isReadingImage.set(true);
    this.errorMessage.set(null);

    try {
      this.showResult(await this.qrScanner.decodeImage(file));
    } catch {
      this.statusMessages.error(statusMessages.unreadableQrImage);
      await this.startCamera();
    } finally {
      this.isReadingImage.set(false);
    }
  }

  protected closeResult(): void {
    this.receiptPreview.set(null);
    void this.startCamera();
  }

  protected async importReceipt(): Promise<void> {
    const preview = this.receiptPreview();
    if (!preview || this.isImporting()) {
      return;
    }

    this.isImporting.set(true);

    try {
      const result = await this.receiptRepository.import(preview.id);
      this.receiptPreview.set(null);
      this.statusMessages.show(statusMessages.receiptImported(result.addedPoints), { kind: 'success' });
      await this.startCamera();
    } finally {
      this.isImporting.set(false);
    }
  }

  private async startCamera(): Promise<void> {
    if (this.isScanning() || this.receiptPreview()) {
      return;
    }

    this.isScanning.set(true);
    this.errorMessage.set(null);

    try {
      await this.qrScanner.startCamera(this.cameraPreview().nativeElement, (result) => void this.showResult(result));
    } catch {
      this.isScanning.set(false);
      this.errorMessage.set('Die Kamera konnte nicht geöffnet werden. Bitte erlauben Sie den Kamerazugriff.');
    }
  }

  private async showResult(result: QrScanResult): Promise<void> {
    this.qrScanner.stop();
    this.isScanning.set(false);
    this.receiptPreview.set(await this.receiptRepository.createPreview(result.rawValue));
  }
}
