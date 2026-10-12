import { Capacitor } from '@capacitor/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { medicationReminderOccurrences } from './medication-reminder-plan';
import { NativeMedicationReminderAdapter } from './native-medication-reminder.adapter';

vi.mock('@capacitor/local-notifications', () => ({
  LocalNotifications: {
    getPending: vi.fn(),
    checkExactNotificationSetting: vi.fn(),
    createChannel: vi.fn(),
    cancel: vi.fn(),
    schedule: vi.fn(),
  },
}));

describe('NativeMedicationReminderAdapter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2026, 9, 8, 15, 30));
    vi.spyOn(Capacitor, 'isNativePlatform').mockReturnValue(true);
    vi.spyOn(Capacitor, 'getPlatform').mockReturnValue('android');
    vi.mocked(LocalNotifications.checkExactNotificationSetting).mockResolvedValue({ exact_alarm: 'granted' });
    vi.mocked(LocalNotifications.createChannel).mockResolvedValue();
    vi.mocked(LocalNotifications.cancel).mockResolvedValue();
    vi.mocked(LocalNotifications.schedule).mockResolvedValue({ notifications: [] });
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('schedules dated wake-capable one-shots rather than a repeating on-rule', async () => {
    vi.mocked(LocalNotifications.getPending).mockResolvedValue({ notifications: [] });

    await new NativeMedicationReminderAdapter().replace([{ id: 830003, hour: 15, minute: 35 }]);

    const scheduled = vi.mocked(LocalNotifications.schedule).mock.calls[0][0].notifications;
    expect(scheduled).toHaveLength(14);
    expect(scheduled[0].schedule).toEqual({ at: new Date(2026, 9, 8, 15, 35), allowWhileIdle: true });
    expect(scheduled[0].isExactNotification).toBe(true);
    expect(scheduled[0].id).not.toBe(830003);
  });

  it('rearms future occurrences once on a cold start, then preserves them on resume', async () => {
    const occurrences = medicationReminderOccurrences([{ id: 830003, hour: 15, minute: 35 }]);
    vi.mocked(LocalNotifications.getPending).mockResolvedValue({
      notifications: occurrences.map(({ id, at }) => ({
        id,
        title: 'Einnahmeerinnerung',
        body: 'Erinnerung',
        schedule: { at },
      })),
    });

    const adapter = new NativeMedicationReminderAdapter();
    await adapter.replace([{ id: 830003, hour: 15, minute: 35 }]);
    expect(vi.mocked(LocalNotifications.schedule).mock.calls[0][0].notifications).toHaveLength(14);
    vi.mocked(LocalNotifications.schedule).mockClear();
    vi.mocked(LocalNotifications.cancel).mockClear();

    await adapter.replace([{ id: 830003, hour: 15, minute: 35 }]);

    expect(LocalNotifications.cancel).toHaveBeenCalledWith({
      notifications: [{ id: 830001 }, { id: 830002 }, { id: 830003 }, { id: 830004 }],
    });
    expect(LocalNotifications.schedule).not.toHaveBeenCalled();
  });

  it('removes legacy repeating alarms without reusing their delivered IDs', async () => {
    vi.mocked(LocalNotifications.getPending).mockResolvedValue({
      notifications: [{ id: 830003, title: 'Einnahmeerinnerung', body: 'Erinnerung', schedule: { on: { hour: 15, minute: 35 } } }],
    });

    await new NativeMedicationReminderAdapter().replace([{ id: 830003, hour: 15, minute: 35 }]);

    expect(LocalNotifications.cancel).toHaveBeenCalledWith({
      notifications: expect.arrayContaining([{ id: 830003 }]),
    });
    expect(vi.mocked(LocalNotifications.schedule).mock.calls[0][0].notifications.every(({ id }) => id !== 830003)).toBe(true);
  });

  it('cancels legacy IDs even when the plugin omits an old alarm from getPending', async () => {
    vi.mocked(LocalNotifications.getPending).mockResolvedValue({ notifications: [] });

    await new NativeMedicationReminderAdapter().replace([{ id: 830003, hour: 15, minute: 35 }]);

    expect(LocalNotifications.cancel).toHaveBeenCalledWith({
      notifications: [{ id: 830001 }, { id: 830002 }, { id: 830003 }, { id: 830004 }],
    });
  });
});
