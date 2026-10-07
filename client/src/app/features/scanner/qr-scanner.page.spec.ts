import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ReceiptRepository, type ReceiptPreview } from '../../core/receipts/receipt.repository';
import { QrScannerService, type QrScanResult, type QrScannerDiagnostics } from '../../core/scanner/qr-scanner.service';
import { ThemeService } from '../../core/theme/theme.service';
import { QrScannerPage } from './qr-scanner.page';

describe('QrScannerPage', () => {
  it('explains a zero-eligible receipt without offering a points import', async () => {
    let onScan: ((result: QrScanResult) => void) | undefined;
    const importReceipt = vi.fn();
    const preview: ReceiptPreview = {
      id: 'receipt-zero',
      rawQrValue: 'test-qr',
      pharmacyName: 'Testapotheke',
      purchasedAt: '28.04.2026, 09:47',
      eligibleCents: 0,
      earnedPoints: 0,
    };

    TestBed.configureTestingModule({
      imports: [QrScannerPage],
      providers: [
        { provide: QrScannerService, useValue: {
          startCamera: async (_preview: HTMLVideoElement, callback: (result: QrScanResult) => void) => {
            onScan = callback;
            return false;
          },
          stop: vi.fn(),
          torchAvailable: signal(false),
          torchEnabled: signal(false),
          diagnostics: signal(null),
          lastScanDiagnostics: signal(null),
        } },
        { provide: ReceiptRepository, useValue: { createPreview: async () => preview, import: importReceipt } },
        { provide: StatusMessageService, useValue: { error: vi.fn(), show: vi.fn() } },
        { provide: ThemeService, useValue: { allowDuplicateReceiptImports: false, showCustomerDebugOutput: false } },
      ],
    });

    const fixture = TestBed.createComponent(QrScannerPage);
    fixture.detectChanges();
    await fixture.whenStable();
    onScan?.({ rawValue: preview.rawQrValue });
    await fixture.whenStable();
    fixture.detectChanges();

    const dialog = (fixture.nativeElement as HTMLElement).querySelector('[role="dialog"]');
    expect(dialog?.textContent).toContain('Keine Punkte für diesen Beleg');
    expect(dialog?.textContent).toContain('keine punktefähigen Posten');
    expect(dialog?.textContent).not.toContain('Punkte gutschreiben');
    expect(dialog?.querySelector('button')?.textContent).toContain('Weiter scannen');
    expect(importReceipt).not.toHaveBeenCalled();
  });

  it('shows the torch control only when supported and includes stream data in debug views', async () => {
    let onScan: ((result: QrScanResult) => void) | undefined;
    const torchEnabled = signal(false);
    const diagnostics: QrScannerDiagnostics = {
      cameraLabel: 'Rear camera',
      width: 1280,
      height: 720,
      frameRate: 30,
      facingMode: 'environment',
      focusMode: 'continuous',
      focusModes: ['continuous'],
      tapFocusAvailable: true,
      torchAvailable: true,
      torchEnabled: false,
      zoomValue: 2,
      zoom: { min: 1, max: 4 },
      contrastValue: 1,
      contrast: { min: 0, max: 2 },
    };
    const lastScanDiagnostics = signal<QrScannerDiagnostics | null>(null);
    const toggleTorch = vi.fn(async () => {
      torchEnabled.set(true);
      return true;
    });
    TestBed.configureTestingModule({
      imports: [QrScannerPage],
      providers: [
        { provide: QrScannerService, useValue: {
          startCamera: async (_preview: HTMLVideoElement, callback: (result: QrScanResult) => void) => {
            onScan = callback;
            return true;
          },
          stop: vi.fn(),
          torchAvailable: signal(true),
          torchEnabled,
          diagnostics: signal(diagnostics),
          lastScanDiagnostics,
          toggleTorch,
        } },
        { provide: ReceiptRepository, useValue: {
          createPreview: async () => ({
            id: 'receipt-test', rawQrValue: 'test-qr', pharmacyName: 'Testapotheke',
            purchasedAt: '07.10.2026', eligibleCents: 100, earnedPoints: 10,
          }),
        } },
        { provide: StatusMessageService, useValue: { error: vi.fn(), show: vi.fn() } },
        { provide: ThemeService, useValue: { allowDuplicateReceiptImports: false, showCustomerDebugOutput: true } },
      ],
    });

    const fixture = TestBed.createComponent(QrScannerPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const root = fixture.nativeElement as HTMLElement;
    const lightButton = root.querySelector<HTMLButtonElement>('[aria-label="Taschenlampe einschalten"]');
    expect(lightButton).not.toBeNull();
    expect(root.querySelector('details')?.textContent).toContain('1280 × 720');
    expect(root.querySelector('details')?.textContent).toContain('Kontrast-Bereich');
    lightButton?.click();
    await fixture.whenStable();
    fixture.detectChanges();
    expect(toggleTorch).toHaveBeenCalledOnce();
    expect(root.querySelector('[aria-label="Taschenlampe ausschalten"]')).not.toBeNull();

    lastScanDiagnostics.set({ ...diagnostics, torchEnabled: true });
    onScan?.({ rawValue: 'test-qr' });
    await fixture.whenStable();
    fixture.detectChanges();
    expect(root.querySelector('[role="dialog"] details')?.textContent).toContain('1280 × 720');
    expect(root.querySelector('[role="dialog"] details')?.textContent).toContain('test-qr');
  });
});
