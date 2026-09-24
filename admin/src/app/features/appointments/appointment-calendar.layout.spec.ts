import type { AdminAppointment, AppointmentBlock } from '../../core/appointments/admin-appointment.service';
import { blockedAppointment, calendarDays, calendarHourBounds, globallyBlockedDay, globallyBlockedSlot, layoutCalendarBlocks, layoutCalendarDay, pharmacyDateKey } from './appointment-calendar.layout';

const appointment = (id: number, startsAt: string, endsAt: string): AdminAppointment => ({
  id,
  customer: `Kunde ${id}`,
  customerId: id,
  type: 'Beratung',
  resource: 'Gerda',
  resourceId: 1,
  resourceColor: '#4b86b0',
  startsAt,
  endsAt,
  status: 'reserved',
  note: null,
  chatConversationId: null,
});

describe('appointment calendar layout', () => {
  it('builds Monday-to-Sunday weeks and keeps the selected day for day view', () => {
    expect(calendarDays('2026-09-24', 'week')).toEqual([
      '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24',
      '2026-09-25', '2026-09-26', '2026-09-27',
    ]);
    expect(calendarDays('2026-09-24', 'day')).toEqual(['2026-09-24']);
    expect(pharmacyDateKey('2026-09-24T22:30:00Z')).toBe('2026-09-25');
  });

  it('places overlapping appointments side by side but gives later independent appointments full width', () => {
    const items = [
      appointment(1, '2026-09-24T10:00:00+02:00', '2026-09-24T11:00:00+02:00'),
      appointment(2, '2026-09-24T10:30:00+02:00', '2026-09-24T11:30:00+02:00'),
      appointment(3, '2026-09-24T11:00:00+02:00', '2026-09-24T12:00:00+02:00'),
      appointment(4, '2026-09-24T12:00:00+02:00', '2026-09-24T12:30:00+02:00'),
    ];

    const events = layoutCalendarDay(items, '2026-09-24', 8);
    expect(events.map((event) => event.appointment.id)).toEqual([1, 2, 3, 4]);
    expect(events.slice(0, 3).map((event) => event.width)).toEqual(['calc(50% - 4px)', 'calc(50% - 4px)', 'calc(50% - 4px)']);
    expect(events[0].left).not.toBe(events[1].left);
    expect(events[0].left).toBe(events[2].left);
    expect(events[3].width).toBe('calc(100% - 4px)');
    expect(events[0].top).toBe(144);
  });

  it('renders full-day and timed blocks and disables only global blocked slots', () => {
    const day = '2026-09-24';
    const blocks: AppointmentBlock[] = [
      { id: 1, typeId: null, startsOn: day, endsOn: day, startsAt: '09:30', endsAt: '10:30', comment: 'Fortbildung' },
      { id: 2, typeId: 4, startsOn: day, endsOn: day, startsAt: null, endsAt: null, comment: null },
    ];
    expect(calendarHourBounds([], [day], blocks)).toEqual({ start: 8, end: 18 });
    const segments = layoutCalendarBlocks(blocks, day, 8, 18);
    expect(segments.map((segment) => segment.block.id)).toEqual([2, 1]);
    expect(segments[0].height).toBe(720);
    expect(segments[1].top).toBe(108);
    expect(segments[1].height).toBe(72);
    expect(globallyBlockedSlot(blocks, day, 9 * 60 + 30)).toBe(true);
    expect(globallyBlockedSlot(blocks, day, 10 * 60 + 30)).toBe(false);
    expect(globallyBlockedSlot(blocks.slice(1), day, 9 * 60 + 30)).toBe(false);
    expect(globallyBlockedDay(blocks, day)).toBe(false);
    expect(blockedAppointment(blocks, day, '11:00', 60, 4)).toBe(true);
    expect(blockedAppointment(blocks, day, '11:00', 60, 5)).toBe(false);
    expect(blockedAppointment(blocks, day, '09:00', 60, 5)).toBe(true);
    expect(blockedAppointment(blocks, day, '10:15', 60, 5)).toBe(true);
    expect(blockedAppointment(blocks, day, '10:30', 60, 5)).toBe(false);
    expect(blockedAppointment([{ ...blocks[0], startsAt: null, endsAt: null }], day, '17:00', 60, 5)).toBe(true);
    expect(globallyBlockedDay([{ ...blocks[0], startsAt: null, endsAt: null }], day)).toBe(true);
  });
});
