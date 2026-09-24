import { PwaInstallService } from './pwa-install.service';

describe('PwaInstallService', () => {
  const originalAgent = Object.getOwnPropertyDescriptor(navigator, 'userAgent');
  const originalMatchMedia = Object.getOwnPropertyDescriptor(window, 'matchMedia');
  let service: PwaInstallService;

  afterEach(() => {
    service?.ngOnDestroy();
    if (originalAgent) Object.defineProperty(navigator, 'userAgent', originalAgent);
    else Reflect.deleteProperty(navigator, 'userAgent');
    if (originalMatchMedia) Object.defineProperty(window, 'matchMedia', originalMatchMedia);
    else Reflect.deleteProperty(window, 'matchMedia');
  });

  function setBrowser(userAgent: string, standalone = false): void {
    Object.defineProperty(navigator, 'userAgent', { configurable: true, value: userAgent });
    Object.defineProperty(window, 'matchMedia', {
      configurable: true,
      value: () => ({ matches: standalone, addEventListener: () => {}, removeEventListener: () => {} }),
    });
  }

  it('shows iOS instructions only in browser mode', () => {
    setBrowser('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)');
    service = new PwaInstallService();
    expect(service.bannerVisible()).toBe(true);
    service.ngOnDestroy();

    setBrowser('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', true);
    service = new PwaInstallService();
    expect(service.bannerVisible()).toBe(false);
  });

  it('uses the Android browser prompt and hides after installation', async () => {
    setBrowser('Mozilla/5.0 (Linux; Android 15; Pixel)');
    service = new PwaInstallService();
    expect(service.bannerVisible()).toBe(false);

    let promptCalls = 0;
    const promptEvent = Object.assign(new Event('beforeinstallprompt', { cancelable: true }), {
      prompt: async () => { promptCalls += 1; },
      userChoice: Promise.resolve({ outcome: 'accepted' as const }),
    });
    window.dispatchEvent(promptEvent);
    expect(promptEvent.defaultPrevented).toBe(true);
    expect(service.bannerVisible()).toBe(true);
    await service.promptInstall();
    expect(promptCalls).toBe(1);
    expect(service.bannerVisible()).toBe(false);

    window.dispatchEvent(new Event('appinstalled'));
    expect(service.installed()).toBe(true);
    expect(service.bannerVisible()).toBe(false);
  });
});
