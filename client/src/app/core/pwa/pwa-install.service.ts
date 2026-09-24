import { computed, Injectable, OnDestroy, signal } from '@angular/core';

interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>;
  readonly userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

type MobilePlatform = 'android' | 'ios' | null;

@Injectable({ providedIn: 'root' })
export class PwaInstallService implements OnDestroy {
  readonly platform: MobilePlatform;
  readonly installed = signal(false);
  readonly promptAvailable = signal(false);
  readonly bannerVisible = computed(() => !this.installed()
    && (this.platform === 'ios' || (this.platform === 'android' && this.promptAvailable())));

  private deferredPrompt: BeforeInstallPromptEvent | null = null;
  private displayModeQuery: MediaQueryList | null = null;
  private readonly onBeforeInstallPrompt = (event: Event): void => {
    if (this.platform !== 'android' || this.installed()) return;
    event.preventDefault();
    this.deferredPrompt = event as BeforeInstallPromptEvent;
    this.promptAvailable.set(true);
  };
  private readonly onAppInstalled = (): void => {
    this.deferredPrompt = null;
    this.promptAvailable.set(false);
    this.installed.set(true);
  };
  private readonly onDisplayModeChange = (): void => this.refreshInstalledState();

  constructor() {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') {
      this.platform = null;
      return;
    }

    const userAgent = navigator.userAgent;
    const isIos = /iPad|iPhone|iPod/i.test(userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    this.platform = isIos ? 'ios' : /Android/i.test(userAgent) ? 'android' : null;
    this.refreshInstalledState();

    window.addEventListener('beforeinstallprompt', this.onBeforeInstallPrompt);
    window.addEventListener('appinstalled', this.onAppInstalled);
    this.displayModeQuery = window.matchMedia?.('(display-mode: standalone)') ?? null;
    this.displayModeQuery?.addEventListener?.('change', this.onDisplayModeChange);
  }

  ngOnDestroy(): void {
    if (typeof window === 'undefined') return;
    window.removeEventListener('beforeinstallprompt', this.onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', this.onAppInstalled);
    this.displayModeQuery?.removeEventListener?.('change', this.onDisplayModeChange);
  }

  async promptInstall(): Promise<void> {
    const prompt = this.deferredPrompt;
    if (prompt === null || this.installed()) return;
    this.deferredPrompt = null;
    this.promptAvailable.set(false);
    await prompt.prompt();
    await prompt.userChoice;
    // A dismissed browser prompt cannot be reused. The browser may offer a new one later.
  }

  private refreshInstalledState(): void {
    const iosStandalone = (navigator as Navigator & { standalone?: boolean }).standalone === true;
    const standalone = window.matchMedia?.('(display-mode: standalone)').matches === true;
    const capacitor = (window as Window & { Capacitor?: { isNativePlatform?: () => boolean } }).Capacitor;
    this.installed.set(iosStandalone || standalone || capacitor?.isNativePlatform?.() === true);
    if (this.installed()) {
      this.deferredPrompt = null;
      this.promptAvailable.set(false);
    }
  }
}
