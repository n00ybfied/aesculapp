import type { Medication } from './medication.service';
import type { CustomerProfile } from '../profile/profile.service';

export interface MedicationReminderSlot {
  readonly id: number;
  readonly hour: number;
  readonly minute: number;
}

export interface MedicationReminderOccurrence {
  readonly id: number;
  readonly at: Date;
}

export const MEDICATION_REMINDER_IDS = [830001, 830002, 830003, 830004] as const;
export const MEDICATION_REMINDER_DAYS_AHEAD = 14;
const OCCURRENCE_ID_BASE = 100_000_000;
const OCCURRENCE_ID_LIMIT = 1_000_000_000;
const MINUTES_PER_DAY = 24 * 60;
const SLOTS_PER_DAY = MEDICATION_REMINDER_IDS.length;

export function isMedicationReminderId(id: number): boolean {
  return MEDICATION_REMINDER_IDS.some((legacyId) => legacyId === id)
    || (Number.isInteger(id) && id >= OCCURRENCE_ID_BASE && id < OCCURRENCE_ID_LIMIT);
}

export function medicationReminderOccurrences(
  slots: readonly MedicationReminderSlot[],
  now: Date = new Date(),
): readonly MedicationReminderOccurrence[] {
  const occurrences: MedicationReminderOccurrence[] = [];
  for (let dayOffset = 0; dayOffset < MEDICATION_REMINDER_DAYS_AHEAD; dayOffset++) {
    for (const slot of slots) {
      const slotIndex = slot.id - MEDICATION_REMINDER_IDS[0];
      if (slotIndex < 0 || slotIndex >= SLOTS_PER_DAY) continue;
      const at = new Date(now.getFullYear(), now.getMonth(), now.getDate() + dayOffset, slot.hour, slot.minute);
      if (at.getTime() <= now.getTime()) continue;
      const dayNumber = Math.floor(Date.UTC(at.getFullYear(), at.getMonth(), at.getDate()) / 86_400_000);
      const id = OCCURRENCE_ID_BASE + dayNumber * SLOTS_PER_DAY * MINUTES_PER_DAY
        + slotIndex * MINUTES_PER_DAY + slot.hour * 60 + slot.minute;
      occurrences.push({ id, at });
    }
  }
  return occurrences;
}

const timeFields = [
  'morningReminderTime',
  'noonReminderTime',
  'eveningReminderTime',
  'nightReminderTime',
] as const;

export function medicationReminderSlots(
  profile: Pick<CustomerProfile, (typeof timeFields)[number]>,
  medications: readonly Pick<Medication, 'schedule'>[],
): readonly MedicationReminderSlot[] {
  const activeSlots = new Set<number>();
  for (const medication of medications) {
    const parts = medication.schedule?.split('-');
    if (parts?.length !== 4) continue;
    parts.forEach((part, index) => {
      const dose = Number(part.replace(',', '.'));
      if (Number.isFinite(dose) && dose > 0) activeSlots.add(index);
    });
  }

  const usedTimes = new Set<string>();
  const reminders: MedicationReminderSlot[] = [];
  for (const index of [...activeSlots].sort()) {
    const time = profile[timeFields[index]];
    const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(time);
    if (!match || usedTimes.has(time)) continue;
    usedTimes.add(time);
    reminders.push({ id: MEDICATION_REMINDER_IDS[index], hour: Number(match[1]), minute: Number(match[2]) });
  }
  return reminders;
}
