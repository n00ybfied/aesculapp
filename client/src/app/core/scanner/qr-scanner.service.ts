import { Injectable } from '@angular/core';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';

export interface QrScanResult {
  readonly rawValue: string;
}

@Injectable({ providedIn: 'root' })
export class QrScannerService {
  private controls: IScannerControls | undefined;
  private session = 0;

  async startCamera(preview: HTMLVideoElement, onResult: (result: QrScanResult) => void): Promise<void> {
    this.stop();
    const scanSession = this.session;
    let hasResult = false;

    // `decodeFromVideoDevice()` verlangt auf manchen Browsern strikt eine
    // Umgebungskamera. Mit idealen Constraints darf der Browser dagegen auf
    // die verfügbare Kamera zurückfallen und bevorzugt auf Mobilgeräten die
    // Rückkamera.
    const reader = new BrowserQRCodeReader(undefined, {
      delayBetweenScanAttempts: 120,
      delayBetweenScanSuccess: 120,
    });
    const controls = await reader.decodeFromConstraints({
      audio: false,
      video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1920, max: 1920 },
        height: { ideal: 1080, max: 1080 },
      },
    }, preview, (result, _error, callbackControls) => {
      if (!result || hasResult || scanSession !== this.session) {
        return;
      }

      hasResult = true;
      callbackControls.stop();
      this.controls = undefined;
      onResult({ rawValue: result.getText() });
    });

    if (hasResult || scanSession !== this.session) {
      controls.stop();
      return;
    }

    this.controls = controls;
  }

  stop(): void {
    this.session += 1;
    this.controls?.stop();
    this.controls = undefined;
  }

  async decodeImage(file: File): Promise<QrScanResult> {
    const imageUrl = URL.createObjectURL(file);

    try {
      const result = await new BrowserQRCodeReader().decodeFromImageUrl(imageUrl);
      return { rawValue: result.getText() };
    } finally {
      URL.revokeObjectURL(imageUrl);
    }
  }
}
