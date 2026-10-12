import { Injectable, NgZone, inject } from '@angular/core';
import { Router } from '@angular/router';
import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';

@Injectable({ providedIn: 'root' })
export class NativePushAdapter {
  private readonly router = inject(Router);
  private readonly zone = inject(NgZone);
  private initialization: Promise<void> | null = null;
  private pendingRegistration: {
    resolve: (token: string) => void;
    reject: (error: Error) => void;
  } | null = null;

  readonly supported = Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android';

  initialize(): Promise<void> {
    if (!this.supported) return Promise.resolve();
    this.initialization ??= this.installListeners();
    return this.initialization;
  }

  async permission(request: boolean): Promise<NotificationPermission> {
    if (!this.supported) return 'denied';
    const status = request
      ? await PushNotifications.requestPermissions()
      : await PushNotifications.checkPermissions();
    return status.receive === 'granted'
      ? 'granted'
      : status.receive === 'denied'
        ? 'denied'
        : 'default';
  }

  async register(): Promise<string> {
    await this.initialize();
    if (this.pendingRegistration !== null) throw new Error('Push registration is already running.');
    return new Promise<string>((resolve, reject) => {
      const timeout = setTimeout(() => {
        this.pendingRegistration = null;
        reject(new Error('Push registration timed out.'));
      }, 20_000);
      this.pendingRegistration = {
        resolve: (token) => {
          clearTimeout(timeout);
          this.pendingRegistration = null;
          resolve(token);
        },
        reject: (error) => {
          clearTimeout(timeout);
          this.pendingRegistration = null;
          reject(error);
        },
      };
      void PushNotifications.register().catch((error: unknown) => {
        this.pendingRegistration?.reject(
          error instanceof Error ? error : new Error('Push registration failed.'),
        );
      });
    });
  }

  async unregister(): Promise<void> {
    if (this.supported) await PushNotifications.unregister();
  }

  private async installListeners(): Promise<void> {
    await PushNotifications.addListener('registration', ({ value }) => {
      if (value) this.pendingRegistration?.resolve(value);
    });
    await PushNotifications.addListener('registrationError', () => {
      this.pendingRegistration?.reject(new Error('Push registration failed.'));
    });
    await PushNotifications.addListener('pushNotificationActionPerformed', () => {
      this.zone.run(() => void this.router.navigateByUrl('/dashboard'));
    });
    await PushNotifications.createChannel({
      id: 'aesculapp-updates',
      name: 'Mitteilungen der Apotheke',
      description: 'Benachrichtigungen der Apotheke',
      importance: 4,
      visibility: 1,
      vibration: true,
    });
  }
}
