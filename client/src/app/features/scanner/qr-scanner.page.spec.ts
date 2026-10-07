import { TestBed } from '@angular/core/testing';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ReceiptRepository, type ReceiptPreview } from '../../core/receipts/receipt.repository';
import { QrScannerService, type QrScanResult } from '../../core/scanner/qr-scanner.service';
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
});
