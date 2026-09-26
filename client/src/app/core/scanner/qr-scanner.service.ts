import { Injectable } from '@angular/core';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';

export interface QrScanResult {
  readonly rawValue: string;
}

interface FocusCapabilities extends MediaTrackCapabilities {
  focusMode?: string[];
}

interface FocusConstraints extends MediaTrackConstraints {
  focusMode?: string;
  pointsOfInterest?: { x: number; y: number }[];
}

interface FocusSupportedConstraints extends MediaTrackSupportedConstraints {
  pointsOfInterest?: boolean;
}

export async function enableCameraFocus(track: MediaStreamTrack): Promise<boolean> {
  const capabilities = track.getCapabilities?.() as FocusCapabilities | undefined;
  const focusModes = capabilities?.focusMode ?? [];

  if (focusModes.includes('continuous')) {
    try {
      await track.applyConstraints({ ...track.getConstraints(), focusMode: 'continuous' } as FocusConstraints);
    } catch {
      // Die Kamera bleibt nutzbar, auch wenn der Browser den Fokusmodus ablehnt.
    }
  }

  const supported = navigator.mediaDevices.getSupportedConstraints() as FocusSupportedConstraints;
  return supported.pointsOfInterest === true
    && (focusModes.includes('continuous') || focusModes.includes('single-shot'));
}

export async function focusCameraAt(track: MediaStreamTrack, x: number, y: number): Promise<boolean> {
  if (track.readyState !== 'live') {
    return false;
  }

  const capabilities = track.getCapabilities?.() as FocusCapabilities | undefined;
  const focusModes = capabilities?.focusMode ?? [];
  const focusMode = focusModes.includes('continuous') ? 'continuous' : 'single-shot';

  try {
    await track.applyConstraints({
      ...track.getConstraints(),
      focusMode,
      pointsOfInterest: [{ x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y)) }],
    } as FocusConstraints);
    return true;
  } catch {
    return false;
  }
}

@Injectable({ providedIn: 'root' })
export class QrScannerService {
  private controls: IScannerControls | undefined;
  private cameraTrack: MediaStreamTrack | undefined;
  private tapFocusAvailable = false;
  private session = 0;

  async startCamera(preview: HTMLVideoElement, onResult: (result: QrScanResult) => void): Promise<boolean> {
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
      this.cameraTrack = undefined;
      this.tapFocusAvailable = false;
      onResult({ rawValue: result.getText() });
    });

    if (hasResult || scanSession !== this.session) {
      controls.stop();
      return false;
    }

    this.controls = controls;
    this.cameraTrack = (preview.srcObject as MediaStream | null)?.getVideoTracks()[0];
    if (this.cameraTrack) {
      try {
        this.tapFocusAvailable = await enableCameraFocus(this.cameraTrack);
      } catch {
        this.tapFocusAvailable = false;
      }
    }

    return scanSession === this.session && this.tapFocusAvailable;
  }

  async focusAt(x: number, y: number): Promise<boolean> {
    if (!this.tapFocusAvailable || !this.cameraTrack) {
      return false;
    }

    return focusCameraAt(this.cameraTrack, x, y);
  }

  stop(): void {
    this.session += 1;
    this.controls?.stop();
    this.controls = undefined;
    this.cameraTrack = undefined;
    this.tapFocusAvailable = false;
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
