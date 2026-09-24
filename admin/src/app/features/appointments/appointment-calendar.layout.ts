import type { AdminAppointment, AppointmentBlock } from '../../core/appointments/admin-appointment.service';

export type CalendarMode = 'week' | 'day';

export interface CalendarInterval {
  readonly appointment: AdminAppointment;
  readonly startMinutes: number;
  readonly endMinutes: number;
}

export interface CalendarEvent extends CalendarInterval {
  readonly top: number;
  readonly height: number;
  readonly left: string;
  readonly width: string;
  readonly startLabel: string;
  readonly endLabel: string;
}

export interface CalendarBlockSegment {
  readonly block: AppointmentBlock;
  readonly top: number;
  readonly height: number;
}

const pharmacyTime = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'Europe/Vienna',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  hourCycle: 'h23',
});

function dateParts(value: string | Date): Record<string, number> {
  const parts = pharmacyTime.formatToParts(typeof value === 'string' ? new Date(value) : value);
  return Object.fromEntries(parts.filter((part) => part.type !== 'literal').map((part) => [part.type, Number(part.value)]));
}

export function pharmacyDateKey(value: string | Date): string {
  const parts = dateParts(value);
  return `${parts['year']}-${String(parts['month']).padStart(2, '0')}-${String(parts['day']).padStart(2, '0')}`;
}

export function pharmacyMinutes(value: Date): number {
  const parts = dateParts(value);
  return parts['hour'] * 60 + parts['minute'];
}

export function shiftCalendarDate(key: string, days: number): string {
  const [year, month, day] = key.split('-').map(Number);
  return new Date(Date.UTC(year, month - 1, day + days, 12)).toISOString().slice(0, 10);
}

export function calendarDays(key: string, mode: CalendarMode): string[] {
  if (mode === 'day') return [key];
  const weekday = new Date(`${key}T12:00:00Z`).getUTCDay();
  const monday = shiftCalendarDate(key, -((weekday + 6) % 7));
  return Array.from({ length: 7 }, (_, index) => shiftCalendarDate(monday, index));
}

export function intervalOnDay(appointment: AdminAppointment, day: string): CalendarInterval | null {
  const startDay = pharmacyDateKey(appointment.startsAt);
  const endDay = pharmacyDateKey(appointment.endsAt);
  if (day < startDay || day > endDay) return null;

  const start = dateParts(appointment.startsAt);
  const end = dateParts(appointment.endsAt);
  const startMinutes = day === startDay ? start['hour'] * 60 + start['minute'] : 0;
  const endMinutes = day === endDay ? end['hour'] * 60 + end['minute'] : 1440;
  return endMinutes > startMinutes ? { appointment, startMinutes, endMinutes } : null;
}

export function calendarHourBounds(appointments: readonly AdminAppointment[], days: readonly string[], blocks: readonly AppointmentBlock[] = []): { start: number; end: number } {
  const intervals = days.flatMap((day) => appointments.map((appointment) => intervalOnDay(appointment, day)).filter((item): item is CalendarInterval => item !== null));
  const blockTimes = days.flatMap((day) => blocks.filter((block) => block.startsOn <= day && block.endsOn >= day && block.startsAt !== null && block.endsAt !== null)
    .flatMap((block) => [timeMinutes(block.startsAt!), timeMinutes(block.endsAt!)]));
  if (intervals.length === 0 && blockTimes.length === 0) return { start: 8, end: 18 };
  return {
    start: Math.max(0, Math.min(8, Math.floor(Math.min(...intervals.map((item) => item.startMinutes), ...blockTimes) / 60))),
    end: Math.min(24, Math.max(18, Math.ceil(Math.max(...intervals.map((item) => item.endMinutes), ...blockTimes) / 60))),
  };
}

export function layoutCalendarBlocks(blocks: readonly AppointmentBlock[], day: string, firstHour: number, lastHour: number, pixelsPerHour = 72): CalendarBlockSegment[] {
  const visibleStart = firstHour * 60;
  const visibleEnd = lastHour * 60;
  return blocks.filter((block) => block.startsOn <= day && block.endsOn >= day).map((block) => {
    const start = block.startsAt === null ? visibleStart : Math.max(visibleStart, timeMinutes(block.startsAt));
    const end = block.endsAt === null ? visibleEnd : Math.min(visibleEnd, timeMinutes(block.endsAt));
    return { block, top: (start - visibleStart) * pixelsPerHour / 60, height: Math.max(0, (end - start) * pixelsPerHour / 60) };
  }).filter((segment) => segment.height > 0).sort((a, b) => Number(a.block.typeId === null) - Number(b.block.typeId === null));
}

export function globallyBlockedSlot(blocks: readonly AppointmentBlock[], day: string, minutes: number, duration = 30): boolean {
  return blockedAppointment(blocks, day, minutesLabel(minutes), duration, null);
}

export function globallyBlockedDay(blocks: readonly AppointmentBlock[], day: string): boolean {
  return blocks.some((block) => block.typeId === null && block.startsOn <= day && block.endsOn >= day
    && block.startsAt === null && block.endsAt === null);
}

export function blockedAppointment(
  blocks: readonly AppointmentBlock[],
  date: string,
  time: string,
  durationMinutes: number,
  typeId: number | null,
): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time) || durationMinutes <= 0) return false;
  const startMinutes = timeMinutes(time);
  if (!Number.isFinite(startMinutes) || startMinutes < 0 || startMinutes >= 1440) return false;

  for (let elapsed = 0; elapsed < durationMinutes;) {
    const absoluteStart = startMinutes + elapsed;
    const dayOffset = Math.floor(absoluteStart / 1440);
    const day = shiftCalendarDate(date, dayOffset);
    const segmentStart = absoluteStart % 1440;
    const segmentEnd = Math.min(1440, startMinutes + durationMinutes - dayOffset * 1440);
    if (blocks.some((block) => (block.typeId === null || block.typeId === typeId)
      && block.startsOn <= day && block.endsOn >= day
      && (block.startsAt === null || (segmentStart < timeMinutes(block.endsAt!) && segmentEnd > timeMinutes(block.startsAt))))) {
      return true;
    }
    elapsed += segmentEnd - segmentStart;
  }
  return false;
}

export function layoutCalendarDay(appointments: readonly AdminAppointment[], day: string, firstHour: number, pixelsPerHour = 72): CalendarEvent[] {
  const intervals = appointments
    .map((appointment) => intervalOnDay(appointment, day))
    .filter((item): item is CalendarInterval => item !== null)
    .sort((a, b) => a.startMinutes - b.startMinutes || b.endMinutes - a.endMinutes || a.appointment.id - b.appointment.id);

  const result: CalendarEvent[] = [];
  let group: CalendarInterval[] = [];
  let groupEnd = -1;

  const flush = (): void => {
    if (group.length === 0) return;
    const columnEnds: number[] = [];
    const columns = group.map((interval) => {
      let column = columnEnds.findIndex((end) => end <= interval.startMinutes);
      if (column === -1) column = columnEnds.length;
      columnEnds[column] = interval.endMinutes;
      return column;
    });
    const width = 100 / columnEnds.length;
    group.forEach((interval, index) => {
      result.push({
        ...interval,
        top: (interval.startMinutes - firstHour * 60) * pixelsPerHour / 60,
        height: (interval.endMinutes - interval.startMinutes) * pixelsPerHour / 60,
        left: `calc(${columns[index] * width}% + 2px)`,
        width: `calc(${width}% - 4px)`,
        startLabel: minutesLabel(interval.startMinutes),
        endLabel: minutesLabel(interval.endMinutes),
      });
    });
    group = [];
    groupEnd = -1;
  };

  for (const interval of intervals) {
    if (group.length > 0 && interval.startMinutes >= groupEnd) flush();
    group.push(interval);
    groupEnd = Math.max(groupEnd, interval.endMinutes);
  }
  flush();
  return result;
}

function minutesLabel(minutes: number): string {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

function timeMinutes(value: string): number {
  const [hours, minutes] = value.split(':').map(Number);
  return hours * 60 + minutes;
}
