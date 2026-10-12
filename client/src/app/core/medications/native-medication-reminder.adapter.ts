import { Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import { MEDICATION_REMINDER_IDS, isMedicationReminderId, medicationReminderOccurrences, type MedicationReminderSlot } from './medication-reminder-plan';

const CHANNEL_ID = 'aesculapp-medication-reminders';

@Injectable({ providedIn: 'root' })
export class NativeMedicationReminderAdapter {
  readonly supported = Capacitor.isNativePlatform();
  private channelReady = false;
  private coldStart = true;

  async listen(onOpen: () => void): Promise<void> {
    if (!this.supported) return;
    await LocalNotifications.addListener('localNotificationActionPerformed', ({ notification }) => {
      if (isMedicationReminderId(notification.id)) onOpen();
    });
  }

  async hasPermission(): Promise<boolean> {
    return this.supported && (await LocalNotifications.checkPermissions()).display === 'granted';
  }

  async requestPermission(): Promise<boolean> {
    return this.supported && (await LocalNotifications.requestPermissions()).display === 'granted';
  }

  async hasExactTiming(): Promise<boolean> {
    if (Capacitor.getPlatform() !== 'android') return true;
    return (await LocalNotifications.checkExactNotificationSetting()).exact_alarm === 'granted';
  }

  async openExactTimingSettings(): Promise<void> {
    if (Capacitor.getPlatform() === 'android') await LocalNotifications.changeExactNotificationSetting();
  }

  async cancel(): Promise<void> {
    if (!this.supported) return;
    const { notifications } = await LocalNotifications.getPending();
    const ids = new Set<number>([
      ...MEDICATION_REMINDER_IDS,
      ...notifications.filter(({ id }) => isMedicationReminderId(id)).map(({ id }) => id),
    ]);
    await LocalNotifications.cancel({ notifications: [...ids].map((id) => ({ id })) });
  }

  async replace(slots: readonly MedicationReminderSlot[]): Promise<'exact' | 'approximate'> {
    if (!this.supported) return 'exact';
    const desired = new Map(medicationReminderOccurrences(slots).map((occurrence) => [occurrence.id, occurrence]));
    const { notifications } = await LocalNotifications.getPending();
    const own = notifications.filter(({ id }) => isMedicationReminderId(id));
    const obsolete = own.filter((notification) => {
      const occurrence = desired.get(notification.id);
      return this.coldStart || occurrence === undefined || notification.schedule?.at === undefined
        || new Date(notification.schedule.at).getTime() !== occurrence.at.getTime();
    });
    // The old repeating alarm can survive without a getPending() record. Cancel its fixed IDs explicitly.
    const obsoleteIds = new Set<number>([...MEDICATION_REMINDER_IDS, ...obsolete.map(({ id }) => id)]);
    await LocalNotifications.cancel({ notifications: [...obsoleteIds].map((id) => ({ id })) });
    const retained = new Set(own.filter(({ id }) => !obsoleteIds.has(id)).map(({ id }) => id));
    const missing = [...desired.values()].filter(({ id }) => !retained.has(id));
    if (missing.length === 0) {
      this.coldStart = false;
      return await this.hasExactTiming() ? 'exact' : 'approximate';
    }

    if (Capacitor.getPlatform() === 'android' && !this.channelReady) {
      await LocalNotifications.createChannel({
        id: CHANNEL_ID,
        name: 'Medikamentenerinnerungen',
        description: 'Lokale Erinnerungen an die eingestellten Einnahmezeiten',
        importance: 4,
        visibility: 0,
      });
      this.channelReady = true;
    }

    const exact = await this.hasExactTiming();
    const result = await LocalNotifications.schedule({
      notifications: missing.map((occurrence) => ({
        id: occurrence.id,
        title: 'Einnahmeerinnerung',
        body: 'Es ist Zeit für Ihre Medikamente. Öffnen Sie Ihren Medikamentenplan.',
        schedule: { at: occurrence.at, allowWhileIdle: true },
        ...(Capacitor.getPlatform() === 'android' ? { channelId: CHANNEL_ID, isExactNotification: exact } : {}),
      })),
    });
    this.coldStart = false;
    return exact && result.warning === undefined ? 'exact' : 'approximate';
  }
}
