import { Component, computed, input, output, signal } from '@angular/core';
import type { AdminAppointment, AppointmentBlock, AppointmentType } from '../../core/appointments/admin-appointment.service';
import {
  calendarDays,
  calendarHourBounds,
  globallyBlockedDay,
  globallyBlockedSlot,
  layoutCalendarDay,
  layoutCalendarBlocks,
  pharmacyDateKey,
  pharmacyMinutes,
  shiftCalendarDate,
  type CalendarEvent,
  type CalendarBlockSegment,
  type CalendarMode,
} from './appointment-calendar.layout';

@Component({
  selector: 'app-appointment-calendar',
  templateUrl: './appointment-calendar.component.html',
  styleUrl: './appointment-calendar.component.css',
})
export class AppointmentCalendarComponent {
  readonly appointments = input.required<readonly AdminAppointment[]>();
  readonly blocks = input.required<readonly AppointmentBlock[]>();
  readonly types = input.required<readonly AppointmentType[]>();
  readonly selected = output<AdminAppointment>();
  readonly createRequested = output<{ date: string; time: string }>();

  protected readonly mode = signal<CalendarMode>(window.matchMedia('(max-width: 760px)').matches ? 'day' : 'week');
  protected readonly selectedDate = signal(pharmacyDateKey(new Date()));
  protected readonly today = pharmacyDateKey(new Date());
  protected readonly days = computed(() => calendarDays(this.selectedDate(), this.mode()));
  protected readonly bounds = computed(() => calendarHourBounds(this.appointments(), this.days(), this.blocks()));
  protected readonly hours = computed(() => Array.from({ length: this.bounds().end - this.bounds().start + 1 }, (_, index) => this.bounds().start + index));
  protected readonly slots = computed(() => Array.from({ length: (this.bounds().end - this.bounds().start) * 2 }, (_, index) => this.bounds().start * 60 + index * 30));
  protected readonly gridHeight = computed(() => (this.bounds().end - this.bounds().start) * 72);
  protected readonly eventsByDay = computed<Record<string, CalendarEvent[]>>(() => Object.fromEntries(
    this.days().map((day) => [day, layoutCalendarDay(this.appointments(), day, this.bounds().start)]),
  ));
  protected readonly blocksByDay = computed<Record<string, CalendarBlockSegment[]>>(() => Object.fromEntries(
    this.days().map((day) => [day, layoutCalendarBlocks(this.blocks(), day, this.bounds().start, this.bounds().end)]),
  ));
  protected readonly periodLabel = computed(() => {
    const days = this.days();
    if (this.mode() === 'day') return this.formatDay(days[0], { dateStyle: 'full' });
    return `${this.formatDay(days[0], { day: 'numeric', month: 'short' })} – ${this.formatDay(days[6], { day: 'numeric', month: 'short', year: 'numeric' })}`;
  });

  protected setMode(mode: CalendarMode): void { this.mode.set(mode); }
  protected move(direction: -1 | 1): void { this.selectedDate.update((day) => shiftCalendarDate(day, direction * (this.mode() === 'week' ? 7 : 1))); }
  protected goToday(): void { this.selectedDate.set(pharmacyDateKey(new Date())); }
  protected chooseDate(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) this.selectedDate.set(value);
  }
  protected openDay(day: string): void { this.selectedDate.set(day); this.mode.set('day'); }
  protected hourTop(hour: number): number { return (hour - this.bounds().start) * 72; }
  protected slotTop(minutes: number): number { return (minutes - this.bounds().start * 60) * 72 / 60; }
  protected slotTime(minutes: number): string { return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`; }
  protected slotUnavailable(day: string, minutes: number): boolean {
    const now = new Date();
    const today = pharmacyDateKey(now);
    return day < today || (day === today && minutes <= pharmacyMinutes(now)) || globallyBlockedSlot(this.blocks(), day, minutes);
  }
  protected pastDay(day: string): boolean { return day < pharmacyDateKey(new Date()); }
  protected blockedDay(day: string): boolean { return globallyBlockedDay(this.blocks(), day); }
  protected blockLabel(block: AppointmentBlock): string {
    const scope = block.typeId === null ? 'Kalender gesperrt' : `Gesperrt: ${this.types().find((type) => type.id === block.typeId)?.title ?? 'Terminart'}`;
    return block.comment ? `${scope} · ${block.comment}` : scope;
  }
  protected createAt(day: string, minutes: number): void {
    if (!this.slotUnavailable(day, minutes)) this.createRequested.emit({ date: day, time: this.slotTime(minutes) });
  }
  protected formatDay(day: string, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat('de-AT', { ...options, timeZone: 'UTC' }).format(new Date(`${day}T12:00:00Z`));
  }
}
