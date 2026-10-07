import { Injectable, signal } from '@angular/core';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';

export interface QrScanResult {
  readonly rawValue: string;
}

export interface ScannerRange {
  readonly min: number;
  readonly max: number;
}

export interface QrScannerDiagnostics {
  readonly cameraLabel: string | null;
  readonly width: number | null;
  readonly height: number | null;
  readonly frameRate: number | null;
  readonly facingMode: string | null;
  readonly focusMode: string | null;
  readonly focusModes: readonly string[];
  readonly tapFocusAvailable: boolean;
  readonly torchAvailable: boolean;
  readonly torchEnabled: boolean;
  readonly zoomValue: number | null;
  readonly zoom: ScannerRange | null;
  readonly contrastValue: number | null;
  readonly contrast: ScannerRange | null;
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

interface ScannerCapabilities extends MediaTrackCapabilities {
  contrast?: ScannerRange;
  focusMode?: string[];
  torch?: boolean;
  zoom?: ScannerRange;
}

interface ScannerSettings extends MediaTrackSettings {
  contrast?: number;
  focusMode?: string;
  zoom?: number;
}

export function readScannerDiagnostics(
  track: MediaStreamTrack,
  preview: HTMLVideoElement,
  tapFocusAvailable: boolean,
  torchAvailable: boolean,
  torchEnabled: boolean,
): QrScannerDiagnostics {
  let capabilities: ScannerCapabilities = {};
  let settings: ScannerSettings = {};
  try {
    capabilities = track.getCapabilities?.() as ScannerCapabilities ?? {};
  } catch {
    // Scanner diagnostics must never interrupt scanning.
  }
  try {
    settings = track.getSettings?.() as ScannerSettings ?? {};
  } catch {
    // Some browsers expose a camera but not its settings.
  }

  return {
    cameraLabel: track.label || null,
    width: preview.videoWidth || settings.width || null,
    height: preview.videoHeight || settings.height || null,
    frameRate: settings.frameRate ?? null,
    facingMode: settings.facingMode ?? null,
    focusMode: settings.focusMode ?? null,
    focusModes: capabilities.focusMode ?? [],
    tapFocusAvailable,
    torchAvailable,
    torchEnabled,
    zoomValue: settings.zoom ?? null,
    zoom: capabilities.zoom ?? null,
    contrastValue: settings.contrast ?? null,
    contrast: capabilities.contrast ?? null,
  };
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
  return focusModes.includes('single-shot')
    || (supported.pointsOfInterest === true && focusModes.includes('continuous'));
}

export async function focusCameraAt(track: MediaStreamTrack, x: number, y: number): Promise<'point' | 'center' | null> {
  if (track.readyState !== 'live') {
    return null;
  }

  const capabilities = track.getCapabilities?.() as FocusCapabilities | undefined;
  const focusModes = capabilities?.focusMode ?? [];
  const pointSupported = (navigator.mediaDevices.getSupportedConstraints() as FocusSupportedConstraints).pointsOfInterest === true;
  const focusMode = focusModes.includes('single-shot') ? 'single-shot' : 'continuous';
  if (!focusModes.includes('single-shot') && (!pointSupported || !focusModes.includes('continuous'))) {
    return null;
  }

  try {
    await track.applyConstraints({
      ...track.getConstraints(),
      focusMode,
      ...(pointSupported ? { pointsOfInterest: [{ x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y)) }] } : {}),
    } as FocusConstraints);
    return pointSupported ? 'point' : 'center';
  } catch {
    return null;
  }
}

@Injectable({ providedIn: 'root' })
export class QrScannerService {
  private controls: IScannerControls | undefined;
  private cameraTrack: MediaStreamTrack | undefined;
  private cameraPreview: HTMLVideoElement | undefined;
  private tapFocusAvailable = false;
  private session = 0;

  readonly diagnostics = signal<QrScannerDiagnostics | null>(null);
  readonly lastScanDiagnostics = signal<QrScannerDiagnostics | null>(null);
  readonly torchAvailable = signal(false);
  readonly torchEnabled = signal(false);

  async startCamera(preview: HTMLVideoElement, onResult: (result: QrScanResult) => void): Promise<boolean> {
    this.stop();
    this.lastScanDiagnostics.set(null);
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
      this.updateDiagnostics();
      this.lastScanDiagnostics.set(this.diagnostics());
      callbackControls.stop();
      this.controls = undefined;
      this.cameraTrack = undefined;
      this.cameraPreview = undefined;
      this.tapFocusAvailable = false;
      this.torchAvailable.set(false);
      this.torchEnabled.set(false);
      this.diagnostics.set(null);
      onResult({ rawValue: result.getText() });
    });

    if (hasResult || scanSession !== this.session) {
      controls.stop();
      return false;
    }

    this.controls = controls;
    this.cameraTrack = (preview.srcObject as MediaStream | null)?.getVideoTracks()[0];
    this.cameraPreview = preview;
    if (this.cameraTrack) {
      try {
        this.torchAvailable.set(controls.switchTorch !== undefined && (this.cameraTrack.getCapabilities?.() as ScannerCapabilities).torch === true);
      } catch {
        this.torchAvailable.set(false);
      }
      try {
        this.tapFocusAvailable = await enableCameraFocus(this.cameraTrack);
      } catch {
        this.tapFocusAvailable = false;
      }
      this.updateDiagnostics();
    }

    return scanSession === this.session && this.tapFocusAvailable;
  }

  async focusAt(x: number, y: number): Promise<'point' | 'center' | null> {
    if (!this.tapFocusAvailable || !this.cameraTrack) {
      return null;
    }

    return focusCameraAt(this.cameraTrack, x, y);
  }

  async toggleTorch(): Promise<boolean> {
    const controls = this.controls;
    if (!this.torchAvailable() || !controls?.switchTorch) {
      return false;
    }

    const scanSession = this.session;
    const enabled = !this.torchEnabled();
    try {
      await controls.switchTorch(enabled);
      if (scanSession !== this.session) {
        if (enabled) {
          await controls.switchTorch(false);
        }
        return false;
      }
      this.torchEnabled.set(enabled);
      this.updateDiagnostics();
      return true;
    } catch {
      return false;
    }
  }

  stop(): void {
    this.session += 1;
    this.controls?.stop();
    this.controls = undefined;
    this.cameraTrack = undefined;
    this.cameraPreview = undefined;
    this.tapFocusAvailable = false;
    this.torchAvailable.set(false);
    this.torchEnabled.set(false);
    this.diagnostics.set(null);
  }

  async decodeImage(file: File): Promise<QrScanResult> {
    this.lastScanDiagnostics.set(null);
    const imageUrl = URL.createObjectURL(file);

    try {
      const result = await new BrowserQRCodeReader().decodeFromImageUrl(imageUrl);
      return { rawValue: result.getText() };
    } finally {
      URL.revokeObjectURL(imageUrl);
    }
  }

  private updateDiagnostics(): void {
    if (!this.cameraTrack || !this.cameraPreview) {
      return;
    }
    this.diagnostics.set(readScannerDiagnostics(
      this.cameraTrack,
      this.cameraPreview,
      this.tapFocusAvailable,
      this.torchAvailable(),
      this.torchEnabled(),
    ));
  }
}
