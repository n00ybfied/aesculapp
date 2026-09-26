import { enableCameraFocus, focusCameraAt } from './qr-scanner.service';

describe('camera focus', () => {
  afterEach(() => vi.unstubAllGlobals());

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
    expect(await focusCameraAt(track, 1.2, -0.3)).toBe(true);
    expect(applyConstraints).toHaveBeenLastCalledWith({
      width: 1920,
      focusMode: 'continuous',
      pointsOfInterest: [{ x: 1, y: 0 }],
    });
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
    expect(await focusCameraAt({ ...track, readyState: 'ended' } as MediaStreamTrack, 0.5, 0.5)).toBe(false);
  });
});
