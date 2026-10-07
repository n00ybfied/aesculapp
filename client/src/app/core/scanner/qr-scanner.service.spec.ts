import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { enableCameraFocus, focusCameraAt, QrScannerService, readScannerDiagnostics } from './qr-scanner.service';

describe('camera focus', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('enables continuous focus and tap focus when supported', async () => {
    const applyConstraints = vi.fn().mockResolvedValue(undefined);
    const track = {
      readyState: 'live',
      getCapabilities: () => ({ focusMode: ['continuous', 'single-shot'] }),
      getConstraints: () => ({ width: 1920 }),
      applyConstraints,
    } as unknown as MediaStreamTrack;
    vi.stubGlobal('navigator', { mediaDevices: { getSupportedConstraints: () => ({ pointsOfInterest: true }) } });

    expect(await enableCameraFocus(track)).toBe(true);
    expect(applyConstraints).toHaveBeenCalledWith({ width: 1920, focusMode: 'continuous' });
    expect(await focusCameraAt(track, 1.2, -0.3)).toBe('point');
    expect(applyConstraints).toHaveBeenLastCalledWith({
      width: 1920,
      focusMode: 'single-shot',
      pointsOfInterest: [{ x: 1, y: 0 }],
    });
  });

  it('triggers single-shot autofocus without point targeting when the browser does not expose it', async () => {
    const applyConstraints = vi.fn().mockResolvedValue(undefined);
    const track = {
      readyState: 'live',
      getCapabilities: () => ({ focusMode: ['continuous', 'single-shot'] }),
      getConstraints: () => ({}),
      applyConstraints,
    } as unknown as MediaStreamTrack;
    vi.stubGlobal('navigator', { mediaDevices: { getSupportedConstraints: () => ({ pointsOfInterest: false }) } });

    expect(await enableCameraFocus(track)).toBe(true);
    expect(await focusCameraAt(track, 0.2, 0.8)).toBe('center');
    expect(applyConstraints).toHaveBeenLastCalledWith({ focusMode: 'single-shot' });
  });

  it('leaves scanning available when browser focus controls are unavailable', async () => {
    const applyConstraints = vi.fn().mockRejectedValue(new Error('unsupported'));
    const track = {
      readyState: 'live',
      getCapabilities: () => ({ focusMode: ['continuous'] }),
      getConstraints: () => ({}),
      applyConstraints,
    } as unknown as MediaStreamTrack;
    vi.stubGlobal('navigator', { mediaDevices: { getSupportedConstraints: () => ({ pointsOfInterest: false }) } });

    expect(await enableCameraFocus(track)).toBe(false);
    expect(await focusCameraAt({ ...track, readyState: 'ended' } as MediaStreamTrack, 0.5, 0.5)).toBe(null);
  });

  it('reports actual stream settings and optional camera capabilities', () => {
    const track = {
      label: 'Rear camera',
      getCapabilities: () => ({ focusMode: ['continuous'], torch: true, zoom: { min: 1, max: 4 }, contrast: { min: 0, max: 2 } }),
      getSettings: () => ({ width: 1280, height: 720, frameRate: 29.97, facingMode: 'environment', focusMode: 'continuous', zoom: 2, contrast: 1 }),
    } as unknown as MediaStreamTrack;
    const preview = { videoWidth: 1280, videoHeight: 720 } as HTMLVideoElement;

    expect(readScannerDiagnostics(track, preview, true, true, false)).toMatchObject({
      cameraLabel: 'Rear camera',
      width: 1280,
      height: 720,
      frameRate: 29.97,
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
    });
  });

  it('switches the supported torch and clears its state when scanning stops', async () => {
    const switchTorch = vi.fn().mockResolvedValue(undefined);
    const stop = vi.fn();
    vi.spyOn(BrowserQRCodeReader.prototype, 'decodeFromConstraints').mockResolvedValue({ stop, switchTorch } as IScannerControls);
    const track = {
      label: 'Rear camera',
      getCapabilities: () => ({ torch: true, focusMode: ['continuous'] }),
      getSettings: () => ({ width: 1280, height: 720 }),
      getConstraints: () => ({}),
      applyConstraints: vi.fn().mockResolvedValue(undefined),
    } as unknown as MediaStreamTrack;
    const preview = document.createElement('video');
    Object.defineProperty(preview, 'srcObject', { value: { getVideoTracks: () => [track] } });
    vi.stubGlobal('navigator', { mediaDevices: { getSupportedConstraints: () => ({ pointsOfInterest: false }) } });
    const service = new QrScannerService();

    await service.startCamera(preview, vi.fn());
    expect(service.torchAvailable()).toBe(true);
    expect(await service.toggleTorch()).toBe(true);
    expect(switchTorch).toHaveBeenCalledWith(true);
    expect(service.torchEnabled()).toBe(true);
    expect(service.diagnostics()?.torchEnabled).toBe(true);

    service.stop();
    expect(stop).toHaveBeenCalled();
    expect(service.torchEnabled()).toBe(false);
    expect(service.torchAvailable()).toBe(false);
  });
});
