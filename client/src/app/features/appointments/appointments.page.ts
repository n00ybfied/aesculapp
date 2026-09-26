import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';

import {
  AppointmentService,
  AppointmentBlock,
  AppointmentSlot,
  AppointmentType,
} from '../../core/appointments/appointment.service';

interface CalendarDay {
  readonly value: string;
  readonly weekday: string;
  readonly day: string;
}

@Component({
  selector: 'app-appointments-page',
  imports: [FormsModule],
  templateUrl: './appointments.page.html',
})
export class AppointmentsPage {
  private readonly appointmentsService = inject(AppointmentService);
  private readonly router = inject(Router);

  protected readonly types = signal<readonly AppointmentType[]>([]);
  protected readonly slots = signal<readonly AppointmentSlot[]>([]);
  protected readonly availableDates = signal<ReadonlySet<string>>(new Set());
  protected readonly blocks = signal<readonly AppointmentBlock[]>([]);
  protected readonly selectedTypeId = signal<number | null>(null);
  protected readonly bookingWindowDays = signal(28);
  protected readonly selectedDate = signal(this.today());
  protected readonly isLoading = signal(true);
  protected readonly isLoadingSlots = signal(false);
  protected readonly isLoadingCalendar = signal(false);
  protected readonly isBooking = signal(false);
  protected readonly selectedSlot = signal<AppointmentSlot | null>(null);
  protected readonly isConfirmationOpen = signal(false);
  protected readonly error = signal('');
  protected readonly message = signal('');
  protected readonly selectedType = computed(() => this.types().find((type) => type.id === this.selectedTypeId()) ?? null);
  protected readonly calendarDays = computed<readonly CalendarDay[]>(() => Array.from({ length: this.bookingWindowDays() + 1 }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() + index);
    return {
      value: this.dateValue(date),
      weekday: new Intl.DateTimeFormat('de-AT', { weekday: 'short' }).format(date),
      day: new Intl.DateTimeFormat('de-AT', { day: 'numeric', month: 'short' }).format(date),
    };
  }));
  protected note = '';

  constructor() {
    void this.load();
  }

  protected selectType(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value);
    this.selectedTypeId.set(Number.isInteger(id) && id > 0 ? id : null);
    this.slots.set([]);
    this.selectedSlot.set(null);
    this.isConfirmationOpen.set(false);
    this.availableDates.set(new Set());
    this.error.set('');
    void this.loadCalendar();
  }

  protected selectDate(date: string): void {
    if (!this.availableDates().has(date)) {
      return;
    }

    this.selectedDate.set(date);
    this.selectedSlot.set(null);
    this.isConfirmationOpen.set(false);
    void this.loadSlots();
  }

  protected selectSlot(slot: AppointmentSlot): void {
    if (!this.isBooking()) this.selectedSlot.set(slot);
  }

  protected openConfirmation(): void {
    if (this.selectedSlot() !== null && !this.isBooking()) this.isConfirmationOpen.set(true);
  }

  protected closeConfirmation(): void {
    if (!this.isBooking()) this.isConfirmationOpen.set(false);
  }

  protected async confirmBooking(): Promise<void> {
    const typeId = this.selectedTypeId();
    const slot = this.selectedSlot();
    if (typeId === null || slot === null || this.isBooking()) {
      return;
    }

    this.isBooking.set(true);
    this.error.set('');
    try {
      await this.appointmentsService.book(typeId, slot.startsAt, this.note.trim());
      this.note = '';
      this.selectedSlot.set(null);
      this.isConfirmationOpen.set(false);
      await this.router.navigateByUrl('/termine/meine');
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && error.status === 409
        && error.error?.code === 'appointment_type_week_limit'
        ? 'Zwischen zwei Terminen derselben Art müssen mindestens sieben Tage liegen.'
        : 'Dieser Termin ist leider nicht mehr verfügbar. Bitte wählen Sie eine andere Uhrzeit.');
      await this.loadSlots();
    } finally {
      this.isBooking.set(false);
    }
  }

  protected formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('de-AT', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value));
  }

  protected formatTime(value: string): string {
    return new Intl.DateTimeFormat('de-AT', { timeStyle: 'short' }).format(new Date(value));
  }

  protected isDateAvailable(date: string): boolean {
    return this.availableDates().has(date);
  }

  private async load(): Promise<void> {
    try {
      const typesResponse = await this.appointmentsService.getTypes();
      this.types.set(typesResponse.types);
      this.bookingWindowDays.set(typesResponse.bookingWindowDays);
    } catch {
      this.error.set('Termine konnten nicht geladen werden. Bitte versuchen Sie es später erneut.');
    } finally {
      this.isLoading.set(false);
    }
  }

  private async loadSlots(): Promise<void> {
    const typeId = this.selectedTypeId();
    const date = this.selectedDate();
    if (typeId === null || date === '') {
      return;
    }

    this.isLoadingSlots.set(true);
    try {
      this.slots.set(await this.appointmentsService.getSlots(typeId, date));
    } catch {
      this.error.set('Freie Termine konnten nicht geladen werden.');
    } finally {
      this.isLoadingSlots.set(false);
    }
  }

  private async loadCalendar(): Promise<void> {
    const typeId = this.selectedTypeId();
    if (typeId === null) {
      return;
    }

    this.isLoadingCalendar.set(true);
    try {
      const dates = this.calendarDays().map((day) => day.value);
      const [slotLists, blocks] = await Promise.all([
        Promise.all(dates.map((date) => this.appointmentsService.getSlots(typeId, date))),
        this.appointmentsService.getBlocks(typeId),
      ]);
      this.blocks.set(blocks);
      const availableDates = new Set(dates.filter((_, index) => slotLists[index].length > 0));
      this.availableDates.set(availableDates);

      const selectedDateIndex = dates.indexOf(this.selectedDate());
      const dateIndex = availableDates.has(this.selectedDate()) ? selectedDateIndex : dates.findIndex((date) => availableDates.has(date));
      this.selectedDate.set(dateIndex >= 0 ? dates[dateIndex] : this.today());
      this.slots.set(dateIndex >= 0 ? slotLists[dateIndex] : []);
    } catch {
      this.error.set('Verfügbare Tage konnten nicht geladen werden.');
    } finally {
      this.isLoadingCalendar.set(false);
    }
  }

  protected blockComment(date: string): string | null {
    return this.blocks().find((block) => block.startsOn <= date && block.endsOn >= date && block.comment !== null)?.comment ?? null;
  }

  private today(): string {
    return this.dateValue(new Date());
  }

  private dateValue(date: Date): string {
    const offset = date.getTimezoneOffset() * 60_000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
  }
}
