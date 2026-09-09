import { AfterViewInit, Component, ElementRef, OnDestroy, inject, signal, viewChild } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { statusMessages } from '../../core/i18n/status-messages';
import { ReceiptRepository, type ReceiptPreview } from '../../core/receipts/receipt.repository';
import { QrScannerService, type QrScanResult } from '../../core/scanner/qr-scanner.service';
import { ThemeService } from '../../core/theme/theme.service';

@Component({
  selector: 'app-qr-scanner-page',
  imports: [CurrencyPipe, NgIcon],
  templateUrl: './qr-scanner.page.html',
})
export class QrScannerPage implements AfterViewInit, OnDestroy {
  private readonly qrScanner = inject(QrScannerService);
  private readonly receiptRepository = inject(ReceiptRepository);
  private readonly statusMessages = inject(StatusMessageService);
  protected readonly theme = inject(ThemeService);
  private readonly cameraPreview = viewChild.required<ElementRef<HTMLVideoElement>>('cameraPreview');

  protected readonly isScanning = signal(false);
  protected readonly isReadingImage = signal(false);
  protected readonly receiptPreview = signal<ReceiptPreview | null>(null);
  protected readonly isImporting = signal(false);
  protected readonly isProcessingResult = signal(false);
  protected readonly isScanPaused = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly unsupportedReceiptMessage = statusMessages.unsupportedReceiptQr;

  async ngAfterViewInit(): Promise<void> {
    await this.startCamera();
  }

  ngOnDestroy(): void {
    this.qrScanner.stop();
  }

  protected async retryCamera(): Promise<void> {
    this.qrScanner.stop();
    this.isScanning.set(false);
    this.isScanPaused.set(false);
    this.errorMessage.set(null);
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
      await this.showResult(await this.qrScanner.decodeImage(file));
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
    } catch (error) {
      if (error instanceof HttpErrorResponse && error.status === 409) {
        this.statusMessages.error(statusMessages.receiptAlreadyImported);
      } else {
        this.statusMessages.error(statusMessages.unsupportedReceiptQr);
      }
    } finally {
      this.isImporting.set(false);
    }
  }

  private async startCamera(): Promise<void> {
    if (this.isScanning() || this.receiptPreview() || this.isProcessingResult()) {
      return;
    }

    this.isScanning.set(true);
    this.isScanPaused.set(false);
    this.errorMessage.set(null);

    try {
      await this.qrScanner.startCamera(this.cameraPreview().nativeElement, (result) => void this.showResult(result));
    } catch {
      this.isScanning.set(false);
      this.errorMessage.set(statusMessages.cameraUnavailable);
    }
  }

  private async showResult(result: QrScanResult): Promise<void> {
    if (this.isProcessingResult() || this.receiptPreview()) {
      return;
    }

    this.qrScanner.stop();
    this.isScanning.set(false);
    this.isProcessingResult.set(true);
    try {
      this.receiptPreview.set(await this.receiptRepository.createPreview(result.rawValue));
    } catch {
      this.statusMessages.error(statusMessages.unsupportedReceiptQr);
      this.isScanPaused.set(true);
    } finally {
      this.isProcessingResult.set(false);
    }
  }
}
