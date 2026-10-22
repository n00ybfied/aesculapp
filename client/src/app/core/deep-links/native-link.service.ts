import { Injectable, NgZone, inject } from '@angular/core';
import { Router } from '@angular/router';
import { App as CapacitorApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { customerLinkOrigin } from '../api/api.config';

const supportedAppIds = new Set([
  'at.floatbox.aesculapp.dev',
  'at.stadtapotheke.trofaiach',
  'at.floatbox.aesculapp.sta.test',
]);

export function isSupportedNativeAppId(id: string): boolean {
  return supportedAppIds.has(id);
}

const tokenPaths = new Set([
  '/passwort-zuruecksetzen',
  '/e-mail-bestaetigen',
  '/e-mail-aendern',
  '/familie',
]);

const customerPaths = new Set([
  '/chat',
  '/familie',
  '/freunde-einladen',
  '/profil/bearbeiten',
  '/termine/meine',
]);

export function nativeCustomerPath(rawUrl: string, expectedOrigin: string): string | null {
  try {
    const url = new URL(rawUrl);
    if (url.origin !== expectedOrigin || url.username || url.password || url.hash) {
      return null;
    }

    const params = url.searchParams;
    if (tokenPaths.has(url.pathname) && params.has('token')) {
      const token = params.get('token');
      return params.size === 1 && token !== null && /^[a-f0-9]{64}$/i.test(token)
        ? `${url.pathname}?token=${encodeURIComponent(token)}`
        : null;
    }

    if (url.pathname === '/registrierung') {
      const referral = params.get('ref');
      return params.size === 1 && referral !== null && /^[a-f0-9]{32}$/.test(referral)
        ? `/registrierung?ref=${referral}`
        : null;
    }

    if (params.size !== 0) {
      return null;
    }
    if (customerPaths.has(url.pathname) || /^\/news\/[1-9]\d*$/.test(url.pathname)) {
      return url.pathname;
    }
    return null;
  } catch {
    return null;
  }
}

@Injectable({ providedIn: 'root' })
export class NativeLinkService {
  private readonly router = inject(Router);
  private readonly zone = inject(NgZone);
  private lastUrl: string | null = null;
  private lastOpenedAt = 0;

  async initialize(): Promise<void> {
    if (!Capacitor.isNativePlatform()) {
      return;
    }

    const { id } = await CapacitorApp.getInfo();
    if (!isSupportedNativeAppId(id)) {
      return;
    }

    await CapacitorApp.addListener('appUrlOpen', ({ url }) => this.open(url, customerLinkOrigin));
    const launch = await CapacitorApp.getLaunchUrl();
    if (launch?.url) {
      this.open(launch.url, customerLinkOrigin);
    }
  }

  private open(url: string, expectedOrigin: string): void {
    const target = nativeCustomerPath(url, expectedOrigin);
    if (target === null) {
      return;
    }

    const now = Date.now();
    if (url === this.lastUrl && now - this.lastOpenedAt < 2_000) {
      return;
    }
    this.lastUrl = url;
    this.lastOpenedAt = now;
    this.zone.run(() => {
      if (this.router.url === target) {
        return;
      }
      void this.router.navigateByUrl(target);
    });
  }
}
