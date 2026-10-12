import {
  CapacitorBarcodeScanner,
  CapacitorBarcodeScannerAndroidScanningLibrary,
  CapacitorBarcodeScannerCameraDirection,
  CapacitorBarcodeScannerTypeHint,
} from '@capacitor/barcode-scanner';
import { NativeQrScannerAdapter } from './native-qr-scanner.adapter';

describe('NativeQrScannerAdapter', () => {
  afterEach(() => vi.restoreAllMocks());

  it('requests a rear-camera QR scan and returns only the raw value', async () => {
    const scan = vi.spyOn(CapacitorBarcodeScanner, 'scanBarcode').mockResolvedValue({
      ScanResult: 'receipt-qr',
      format: CapacitorBarcodeScannerTypeHint.QR_CODE,
    });

    expect(await new NativeQrScannerAdapter().scan()).toBe('receipt-qr');
    expect(scan).toHaveBeenCalledWith(expect.objectContaining({
      hint: CapacitorBarcodeScannerTypeHint.QR_CODE,
      cameraDirection: CapacitorBarcodeScannerCameraDirection.BACK,
      android: { scanningLibrary: CapacitorBarcodeScannerAndroidScanningLibrary.MLKIT },
    }));
  });

  it('treats an empty scan as cancellation', async () => {
    vi.spyOn(CapacitorBarcodeScanner, 'scanBarcode').mockResolvedValue({
      ScanResult: '',
      format: CapacitorBarcodeScannerTypeHint.QR_CODE,
    });
    expect(await new NativeQrScannerAdapter().scan()).toBeNull();
  });

  it('treats the native cancel button as cancellation but passes through other errors', async () => {
    const scan = vi.spyOn(CapacitorBarcodeScanner, 'scanBarcode');
    scan.mockRejectedValueOnce({ code: 'OS-PLUG-BARC-0006', message: 'Cancelled' });
    expect(await new NativeQrScannerAdapter().scan()).toBeNull();

    const cameraError = { code: 'CAMERA_UNAVAILABLE' };
    scan.mockRejectedValueOnce(cameraError);
    await expect(new NativeQrScannerAdapter().scan()).rejects.toBe(cameraError);
  });
});
