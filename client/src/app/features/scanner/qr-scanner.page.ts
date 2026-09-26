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
  protected readonly canTapFocus = signal(false);
  protected readonly focusPoint = signal<{ x: number; y: number } | null>(null);
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

  protected async focusPreview(event: MouseEvent): Promise<void> {
    if (!this.isScanning() || !this.canTapFocus()) {
      return;
    }

    const video = this.cameraPreview().nativeElement;
    const bounds = video.getBoundingClientRect();
    if (!video.videoWidth || !video.videoHeight || !bounds.width || !bounds.height) {
      return;
    }
    const displayX = event.detail === 0 ? 0.5 : (event.clientX - bounds.left) / bounds.width;
    const displayY = event.detail === 0 ? 0.5 : (event.clientY - bounds.top) / bounds.height;
    const scale = Math.max(bounds.width / video.videoWidth, bounds.height / video.videoHeight);
    const visibleWidth = video.videoWidth * scale;
    const visibleHeight = video.videoHeight * scale;
    const x = event.detail === 0 ? 0.5 : (event.clientX - bounds.left + (visibleWidth - bounds.width) / 2) / visibleWidth;
    const y = event.detail === 0 ? 0.5 : (event.clientY - bounds.top + (visibleHeight - bounds.height) / 2) / visibleHeight;
    if (await this.qrScanner.focusAt(x, y)) {
      this.focusPoint.set({ x: displayX * 100, y: displayY * 100 });
      window.setTimeout(() => this.focusPoint.set(null), 900);
    }
  }

  protected async retryCamera(): Promise<void> {
    this.qrScanner.stop();
    this.isScanning.set(false);
    this.canTapFocus.set(false);
    this.focusPoint.set(null);
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
    this.canTapFocus.set(false);
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
      this.receiptPreview.set(null);
      if (error instanceof HttpErrorResponse && error.status === 409) {
        this.statusMessages.error(statusMessages.receiptAlreadyImported);
      } else {
        this.statusMessages.error(statusMessages.unsupportedReceiptQr);
      }
      await this.startCamera();
    } finally {
      this.isImporting.set(false);
    }
  }

  private async startCamera(): Promise<void> {
    if (this.isScanning() || this.receiptPreview() || this.isProcessingResult()) {
      return;
    }

    this.isScanning.set(true);
    this.canTapFocus.set(false);
    this.focusPoint.set(null);
    this.isScanPaused.set(false);
    this.errorMessage.set(null);

    try {
      this.canTapFocus.set(await this.qrScanner.startCamera(this.cameraPreview().nativeElement, (result) => void this.showResult(result)));
    } catch {
      this.isScanning.set(false);
      this.canTapFocus.set(false);
      this.errorMessage.set(statusMessages.cameraUnavailable);
    }
  }

  private async showResult(result: QrScanResult): Promise<void> {
    if (this.isProcessingResult() || this.receiptPreview()) {
      return;
    }

    this.qrScanner.stop();
    this.isScanning.set(false);
    this.canTapFocus.set(false);
    this.focusPoint.set(null);
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
