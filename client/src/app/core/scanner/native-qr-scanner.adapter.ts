import { Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';

@Injectable({ providedIn: 'root' })
export class NativeQrScannerAdapter {
  readonly supported = Capacitor.isNativePlatform();

  async scan(): Promise<string | null> {
    try {
      const {
        CapacitorBarcodeScanner,
        CapacitorBarcodeScannerAndroidScanningLibrary,
        CapacitorBarcodeScannerCameraDirection,
        CapacitorBarcodeScannerTypeHint,
      } = await import('@capacitor/barcode-scanner');
      const result = await CapacitorBarcodeScanner.scanBarcode({
        hint: CapacitorBarcodeScannerTypeHint.QR_CODE,
        cameraDirection: CapacitorBarcodeScannerCameraDirection.BACK,
        scanInstructions: 'QR-Code der Rechnung scannen',
        cancelButtonAccessibilityLabel: 'Scan abbrechen',
        torchButtonOnAccessibilityLabel: 'Taschenlampe ausschalten',
        torchButtonOffAccessibilityLabel: 'Taschenlampe einschalten',
        android: { scanningLibrary: CapacitorBarcodeScannerAndroidScanningLibrary.MLKIT },
      });

      return result.ScanResult || null;
    } catch (error: unknown) {
      // Der X-Button der Android-Scanneransicht meldet diesen Plugin-Code.
      if (typeof error === 'object' && error !== null && 'code' in error && error.code === 'OS-PLUG-BARC-0006') {
        return null;
      }
      throw error;
    }
  }
}
