import { describe, expect, it } from 'vitest';
import { isMedicationReminderId, medicationReminderOccurrences, medicationReminderSlots } from './medication-reminder-plan';

const profile = {
  morningReminderTime: '08:15',
  noonReminderTime: '12:00',
  eveningReminderTime: '18:30',
  nightReminderTime: '22:00',
};

describe('medicationReminderSlots', () => {
  it('plans only times with a positive dose and combines medications at the same time', () => {
    expect(medicationReminderSlots(profile, [
      { schedule: '1-0-0-0' },
      { schedule: '0.5-0-1-0' },
      { schedule: '0-0-0-0' },
    ])).toEqual([
      { id: 830001, hour: 8, minute: 15 },
      { id: 830003, hour: 18, minute: 30 },
    ]);
  });

  it('does not schedule two notifications when two intake slots use the same time', () => {
    expect(medicationReminderSlots({ ...profile, noonReminderTime: '08:15' }, [
      { schedule: '1-1-0-0' },
    ])).toEqual([{ id: 830001, hour: 8, minute: 15 }]);
  });

  it('ignores invalid schedules and times', () => {
    expect(medicationReminderSlots({ ...profile, morningReminderTime: '25:00' }, [
      { schedule: '1-0-0-0' },
      { schedule: 'invalid' },
      { schedule: null },
    ])).toEqual([]);
    expect(medicationReminderSlots({ ...profile, morningReminderTime: '' }, [
      { schedule: '1-0-0-0' },
    ])).toEqual([]);
  });

  it('creates dated one-shot occurrences with distinct IDs and skips elapsed times', () => {
    const now = new Date(2026, 9, 8, 18, 31);
    const slots = medicationReminderSlots(profile, [{ schedule: '1-0-1-0' }]);
    const occurrences = medicationReminderOccurrences(slots, now);

    expect(occurrences).toHaveLength(26);
    expect(occurrences[0].at).toEqual(new Date(2026, 9, 9, 8, 15));
    expect(occurrences[1].at).toEqual(new Date(2026, 9, 9, 18, 30));
    expect(new Set(occurrences.map(({ id }) => id)).size).toBe(occurrences.length);
    expect(occurrences.every(({ id }) => isMedicationReminderId(id))).toBe(true);
  });

  it('does not reuse a delivered notification ID when the time changes', () => {
    const now = new Date(2026, 9, 8, 15, 0);
    const oldId = medicationReminderOccurrences([{ id: 830003, hour: 15, minute: 35 }], now)[0].id;
    const newId = medicationReminderOccurrences([{ id: 830003, hour: 15, minute: 40 }], now)[0].id;

    expect(newId).not.toBe(oldId);
    expect(isMedicationReminderId(830003)).toBe(true);
  });
});
