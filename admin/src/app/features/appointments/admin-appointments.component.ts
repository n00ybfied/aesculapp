import { Component, ElementRef, OnDestroy, OnInit, computed, inject, signal, viewChild } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

import {
  AdminAppointment,
  AdminAppointmentService,
  AppointmentAvailability,
  AppointmentBlock,
  AppointmentResource,
  AppointmentType,
  CustomerCancellationNotice,
  AppointmentStaffUser,
} from '../../core/appointments/admin-appointment.service';
import { AppointmentCalendarComponent } from './appointment-calendar.component';
import { AppointmentCreateComponent } from './appointment-create.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

type AppointmentModal = 'type' | 'resource' | 'availability' | 'block' | null;

interface BlockCalendarDay {
  readonly value: string;
  readonly day: number;
}

@Component({
  standalone: true,
  imports: [FormsModule, AppointmentCalendarComponent, AppointmentCreateComponent],
  templateUrl: './admin-appointments.component.html',
  styleUrl: './admin-appointments.component.css',
})
export class AdminAppointmentsComponent implements OnInit, OnDestroy {
  private readonly service = inject(AdminAppointmentService);
  private readonly dialogs = inject(ConfirmDialogService);
  private readonly auth = inject(AdminAuthService);
  protected readonly canAccessChat = () => this.auth.canAccess('chat');
  protected readonly isTenantAdmin = this.auth.isTenantAdmin;
  protected canConfirmAppointment(appointment: AdminAppointment): boolean {
    return this.isTenantAdmin() && appointment.status === 'pending_staff_confirmation' && new Date(appointment.startsAt).getTime() > Date.now();
  }
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly detailCloseButton = viewChild<ElementRef<HTMLButtonElement>>('detailCloseButton');
  private detailTrigger: HTMLElement | null = null;
  private createTrigger: HTMLElement | null = null;
  private pollTimer: ReturnType<typeof setInterval> | null = null;
  private polling = false;
  private destroyed = false;
  private appointmentsRequest = 0;
  private lastSeenInView = -1;
  private dismissedCancellationId = 0;
  private readonly onVisibilityChange = () => {
    if (!document.hidden) void this.refreshAppointments();
  };

  protected readonly appointments = signal<readonly AdminAppointment[]>([]);
  protected readonly cancellationNotices = signal<readonly CustomerCancellationNotice[]>([]);
  protected readonly visibleCancellationNotices = computed(() => this.cancellationNotices().slice(0, 3));
  protected readonly dismissingCancellations = signal(false);
  protected readonly selectedAppointmentId = signal<number | null>(null);
  protected readonly creatingAppointment = signal(false);
  protected readonly appointmentPrefill = signal<{ date: string; time: string } | null>(null);
  protected readonly selectedAppointment = computed(() => this.appointments().find((appointment) => appointment.id === this.selectedAppointmentId()) ?? null);
  protected readonly types = signal<readonly AppointmentType[]>([]);
  protected readonly resources = signal<readonly AppointmentResource[]>([]);
  protected readonly staffUsers = signal<readonly AppointmentStaffUser[]>([]);
  protected readonly availableChats = signal<readonly { id: number; status: string; updatedAt: string }[]>([]);
  protected readonly loadingChats = signal(false);
  protected readonly linkingChat = signal(false);
  protected readonly confirmingAppointment = signal<number | null>(null);
  protected selectedChatId = '';
  protected readonly availability = signal<readonly AppointmentAvailability[]>([]);
  protected readonly blocks = signal<readonly AppointmentBlock[]>([]);
  protected readonly modal = signal<AppointmentModal>(null);
  protected readonly loading = signal(true);
  protected readonly message = signal('');
  protected readonly error = signal('');

  protected typeTitle = '';
  protected typeDuration = 15;
  protected typeBuffer = 5;
  protected resourceName = '';
  protected resourceUserId = '';
  protected resourceColor = '#4b86b0';
  protected availabilityResourceId = '';
  protected availabilityTypeId = '';
  protected availabilityStartsAt = '09:00';
  protected availabilityEndsAt = '12:00';
  protected blockTypeId = '';
  protected blockStartsOn = '';
  protected blockEndsOn = '';
  protected blockHasTime = false;
  protected blockStartsAt = '09:00';
  protected blockEndsAt = '12:00';
  protected blockComment = '';
  protected blockDateSelectionStarted = false;
  protected readonly blockCalendarMonth = signal(this.monthStart(new Date()));
  protected readonly blockCalendarLabel = computed(() => new Intl.DateTimeFormat('de-AT', { month: 'long', year: 'numeric' }).format(this.blockCalendarMonth()));
  protected readonly blockCalendarDays = computed<readonly (BlockCalendarDay | null)[]>(() => {
    const month = this.blockCalendarMonth();
    const firstWeekday = (month.getDay() + 6) % 7;
    const daysInMonth = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
    return Array.from({ length: 42 }, (_, index) => {
      const day = index - firstWeekday + 1;
      if (day < 1 || day > daysInMonth) return null;
      const date = new Date(month.getFullYear(), month.getMonth(), day);
      return { value: this.dateValue(date), day };
    });
  });
  protected editingTypeId: number | null = null;
  protected editingResourceId: number | null = null;
  protected editingAvailabilityId: number | null = null;
  protected editingBlockId: number | null = null;
  protected readonly weekdays = [
    { id: 1, label: 'Mo' },
    { id: 2, label: 'Di' },
    { id: 3, label: 'Mi' },
    { id: 4, label: 'Do' },
    { id: 5, label: 'Fr' },
    { id: 6, label: 'Sa' },
    { id: 7, label: 'So' },
  ] as const;
  protected weekdaySelected: Record<number, boolean> = {
    1: true,
    2: true,
    3: true,
    4: true,
    5: true,
    6: false,
    7: false,
  };

  async ngOnInit(): Promise<void> {
    await this.load();
    if (this.destroyed) return;
    const appointmentId = Number(this.route.snapshot.queryParamMap.get('appointmentId'));
    const linkedAppointment = this.appointments().find((item) => item.id === appointmentId);
    if (linkedAppointment) this.openAppointmentDetails(linkedAppointment);
    this.pollTimer = setInterval(() => void this.refreshAppointments(), 5_000);
    document.addEventListener('visibilitychange', this.onVisibilityChange);
  }

  ngOnDestroy(): void {
    this.destroyed = true;
    if (this.pollTimer !== null) clearInterval(this.pollTimer);
    document.removeEventListener('visibilitychange', this.onVisibilityChange);
  }

  protected openModal(modal: Exclude<AppointmentModal, null>): void {
    this.error.set('');
    this.modal.set(modal);
  }

  protected createModal(modal: Exclude<AppointmentModal, null>): void {
    if (modal === 'type') {
      this.editingTypeId = null;
    }
    if (modal === 'resource') {
      this.editingResourceId = null;
      this.resourceName = '';
      this.resourceUserId = '';
      const palette = ['#4b86b0', '#9664b3', '#28887d', '#bb7042', '#c05283', '#5477b9', '#6f9148', '#ae5d5d'];
      this.resourceColor = palette.find((color) => !this.resources().some((resource) => resource.color === color))
        ?? palette[this.resources().length % palette.length];
    }
    if (modal === 'availability') {
      this.editingAvailabilityId = null;
    }
    if (modal === 'block') {
      this.editingBlockId = null;
      this.blockTypeId = '';
      this.blockStartsOn = '';
      this.blockEndsOn = '';
      this.blockCalendarMonth.set(this.monthStart(new Date()));
      this.blockHasTime = false;
      this.blockComment = '';
      this.blockDateSelectionStarted = false;
    }
    this.openModal(modal);
  }

  protected modalTitle(modal: Exclude<AppointmentModal, null>): string {
    if (modal === 'type') {
      return this.editingTypeId === null ? 'Terminart anlegen' : 'Terminart bearbeiten';
    }
    if (modal === 'resource') {
      return this.editingResourceId === null ? 'Person anlegen' : 'Person bearbeiten';
    }
    if (modal === 'availability') {
      return this.editingAvailabilityId === null ? 'Verfügbarkeit anlegen' : 'Verfügbarkeit bearbeiten';
    }
    return this.editingBlockId === null ? 'Sperrzeit anlegen' : 'Sperrzeit bearbeiten';
  }

  protected editType(type: AppointmentType): void {
    this.editingTypeId = type.id;
    this.typeTitle = type.title;
    this.typeDuration = type.durationMinutes;
    this.typeBuffer = type.bufferMinutes;
    this.openModal('type');
  }

  protected editResource(resource: AppointmentResource): void {
    this.editingResourceId = resource.id;
    this.resourceName = resource.name;
    this.resourceUserId = resource.userId === null ? '' : String(resource.userId);
    this.resourceColor = resource.color;
    this.openModal('resource');
  }

  protected editAvailability(rule: AppointmentAvailability): void {
    this.editingAvailabilityId = rule.id;
    this.availabilityResourceId = String(rule.resourceId);
    this.availabilityTypeId = String(rule.typeId);
    this.availabilityStartsAt = rule.startsAt;
    this.availabilityEndsAt = rule.endsAt;
    this.weekdaySelected = Object.fromEntries(this.weekdays.map((day) => [day.id, rule.weekdays.includes(day.id)]));
    this.openModal('availability');
  }

  protected editBlock(block: AppointmentBlock): void {
    this.editingBlockId = block.id;
    this.blockTypeId = block.typeId === null ? '' : String(block.typeId);
    this.blockStartsOn = block.startsOn;
    this.blockEndsOn = block.endsOn;
    this.blockHasTime = block.startsAt !== null && block.endsAt !== null;
    this.blockStartsAt = block.startsAt ?? '09:00';
    this.blockEndsAt = block.endsAt ?? '12:00';
    this.blockComment = block.comment ?? '';
    this.blockDateSelectionStarted = true;
    this.blockCalendarMonth.set(this.monthStart(new Date(`${block.startsOn}T00:00:00`)));
    this.openModal('block');
  }

  protected closeModal(): void {
    this.modal.set(null);
  }

  protected onResourceUserChange(): void {
    const user = this.staffUsers().find((item) => item.id === Number(this.resourceUserId));
    if (user) this.resourceName = user.displayName;
  }

  protected openAppointmentDetails(appointment: AdminAppointment): void {
    this.error.set('');
    this.detailTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    this.selectedAppointmentId.set(appointment.id);
    this.selectedChatId = '';
    this.availableChats.set([]);
    if (appointment.customerId !== null && appointment.chatConversationId === null && this.canAccessChat()) {
      void this.loadAvailableChats(appointment.id);
    }
    setTimeout(() => this.detailCloseButton()?.nativeElement.focus());
  }

  private async loadAvailableChats(id: number): Promise<void> {
    this.loadingChats.set(true);
    try {
      const result = await this.service.listChats(id);
      if (this.selectedAppointmentId() === id) this.availableChats.set(result.conversations);
    } catch {
      if (this.selectedAppointmentId() === id) this.error.set('Gespräche konnten nicht geladen werden.');
    } finally {
      this.loadingChats.set(false);
    }
  }

  protected async openAppointmentChat(appointment: AdminAppointment): Promise<void> {
    if (this.linkingChat()) return;
    if (appointment.chatConversationId !== null) {
      await this.router.navigate(['/chat'], { queryParams: { conversationId: appointment.chatConversationId } });
      return;
    }
    this.linkingChat.set(true);
    this.error.set('');
    try {
      const selected = this.selectedChatId === '' ? null : Number(this.selectedChatId);
      const result = await this.service.linkChat(appointment.id, selected);
      this.appointments.update((items) => items.map((item) => item.id === appointment.id ? { ...item, chatConversationId: result.conversationId } : item));
      await this.router.navigate(['/chat'], { queryParams: { conversationId: result.conversationId } });
    } catch (error) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
        ? error.error.message : 'Das Gespräch konnte nicht eröffnet werden.');
    } finally {
      this.linkingChat.set(false);
    }
  }

  protected closeAppointmentDetails(): void {
    this.selectedAppointmentId.set(null);
    this.detailTrigger?.focus();
    this.detailTrigger = null;
  }

  protected openAppointmentCreate(prefill: { date: string; time: string } | null = null): void {
    this.error.set('');
    this.createTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    this.appointmentPrefill.set(prefill);
    this.creatingAppointment.set(true);
  }

  protected closeAppointmentCreate(): void {
    this.creatingAppointment.set(false);
    this.appointmentPrefill.set(null);
    this.createTrigger?.focus();
    this.createTrigger = null;
  }

  protected async appointmentCreated(): Promise<void> {
    this.closeAppointmentCreate();
    await this.load();
    this.message.set('Termin wurde angelegt.');
  }

  protected async dismissCustomerCancellations(): Promise<void> {
    const throughId = this.cancellationNotices().at(-1)?.id;
    if (throughId === undefined || this.dismissingCancellations()) return;
    this.dismissingCancellations.set(true);
    try {
      await this.service.acknowledgeCustomerCancellations(throughId);
      this.dismissedCancellationId = Math.max(this.dismissedCancellationId, throughId);
      this.cancellationNotices.update((items) => items.filter((item) => item.id > throughId));
    } catch {
      this.error.set('Der Hinweis konnte nicht geschlossen werden. Bitte erneut versuchen.');
    } finally {
      this.dismissingCancellations.set(false);
    }
  }

  protected async saveType(): Promise<void> {
    if (this.typeTitle.trim() === '' || this.typeDuration < 5 || this.typeBuffer < 0) {
      this.error.set('Bitte Bezeichnung, Dauer und Puffer prüfen.');
      return;
    }

    try {
      const data = { title: this.typeTitle.trim(), durationMinutes: this.typeDuration, bufferMinutes: this.typeBuffer };
      if (this.editingTypeId === null) {
        await this.service.createType(data);
      } else {
        await this.service.updateType(this.editingTypeId, { ...data, isVisible: true });
      }
      this.typeTitle = '';
      this.editingTypeId = null;
      this.closeModal();
      await this.load();
      this.message.set('Terminart wurde gespeichert.');
    } catch {
      this.error.set('Die Terminart konnte nicht gespeichert werden.');
    }
  }

  protected async saveResource(): Promise<void> {
    if (this.resourceName.trim() === '' || !/^#[0-9a-fA-F]{6}$/.test(this.resourceColor)) {
      this.error.set('Bitte einen Namen und eine gültige Farbe eingeben.');
      return;
    }

    try {
      const data = { name: this.resourceName.trim(), color: this.resourceColor, userId: this.resourceUserId === '' ? null : Number(this.resourceUserId) };
      if (this.editingResourceId === null) {
        await this.service.createResource(data);
      } else {
        await this.service.updateResource(this.editingResourceId, { ...data, isActive: true });
      }
      this.resourceName = '';
      this.resourceUserId = '';
      this.resourceColor = '#4b86b0';
      this.editingResourceId = null;
      this.closeModal();
      await this.load();
      this.message.set('Person wurde gespeichert.');
    } catch {
      this.error.set('Die Person konnte nicht gespeichert werden.');
    }
  }

  protected async saveAvailability(): Promise<void> {
    const weekdays = this.weekdays
      .filter((day) => this.weekdaySelected[day.id])
      .map((day) => day.id);
    const resourceId = Number(this.availabilityResourceId);
    const typeId = Number(this.availabilityTypeId);

    if (!Number.isInteger(resourceId) || !Number.isInteger(typeId) || weekdays.length === 0 || this.availabilityStartsAt >= this.availabilityEndsAt) {
      this.error.set('Bitte Person, Terminart, mindestens einen Wochentag und einen gültigen Zeitraum auswählen.');
      return;
    }

    try {
      if (this.editingAvailabilityId === null) {
        await this.service.createAvailability({ resourceId, typeId, weekdays, startsAt: this.availabilityStartsAt, endsAt: this.availabilityEndsAt });
      } else {
        await this.service.updateAvailability(this.editingAvailabilityId, { resourceId, typeId, weekdays, startsAt: this.availabilityStartsAt, endsAt: this.availabilityEndsAt });
      }
      this.editingAvailabilityId = null;
      this.closeModal();
      await this.load();
      this.message.set('Verfügbarkeit wurde gespeichert.');
    } catch {
      this.error.set('Die Verfügbarkeit konnte nicht gespeichert werden.');
    }
  }

  protected async cancel(appointment: AdminAppointment): Promise<void> {
    if (appointment.status === 'cancelled' || !await this.dialogs.confirm(`Termin von ${appointment.customer} wirklich absagen?`, { title: 'Termin absagen', confirmLabel: 'Termin absagen', destructive: true })) {
      return;
    }

    try {
      await this.service.cancel(appointment.id);
      await this.load();
      this.closeAppointmentDetails();
      this.message.set('Termin wurde abgesagt.');
    } catch {
      this.error.set('Der Termin konnte nicht abgesagt werden.');
    }
  }

  protected async confirmAppointment(appointment: AdminAppointment): Promise<void> {
    if (!this.canConfirmAppointment(appointment) || this.confirmingAppointment() !== null) return;
    this.confirmingAppointment.set(appointment.id);
    this.error.set('');
    try {
      await this.service.confirm(appointment.id);
      this.appointments.update((items) => items.map((item) => item.id === appointment.id ? { ...item, status: 'reserved' } : item));
      this.message.set('Termin wurde bestätigt.');
    } catch (failure: unknown) {
      this.error.set(failure instanceof HttpErrorResponse && typeof failure.error?.message === 'string'
        ? failure.error.message : 'Der Termin konnte nicht bestätigt werden.');
    } finally {
      this.confirmingAppointment.set(null);
    }
  }

  protected async saveBlock(): Promise<void> {
    if (this.blockStartsOn === '' || this.blockEndsOn === '' || this.blockStartsOn > this.blockEndsOn || (this.blockHasTime && this.blockStartsAt >= this.blockEndsAt)) {
      this.error.set('Bitte einen gültigen Zeitraum auswählen.');
      return;
    }
    const data = { typeId: this.blockTypeId === '' ? null : Number(this.blockTypeId), startsOn: this.blockStartsOn, endsOn: this.blockEndsOn, hasTime: this.blockHasTime, startsAt: this.blockStartsAt, endsAt: this.blockEndsAt, comment: this.blockComment.trim() };
    try {
      if (this.editingBlockId === null) await this.service.createBlock(data);
      else await this.service.updateBlock(this.editingBlockId, data);
      this.closeModal();
      await this.load();
      this.message.set('Sperrzeit wurde gespeichert.');
    } catch { this.error.set('Die Sperrzeit konnte nicht gespeichert werden.'); }
  }

  protected isBlockDayAvailable(date: string): boolean {
    if (date < this.today()) {
      return false;
    }
    const weekday = ((new Date(`${date}T00:00:00`).getDay() + 6) % 7) + 1;
    const selectedTypeId = Number(this.blockTypeId);
    return this.availability().some((rule) => {
      if (!rule.weekdays.includes(weekday) || (selectedTypeId > 0 && rule.typeId !== selectedTypeId)) {
        return false;
      }
      return true;
    });
  }

  protected selectBlockDay(date: string): void {
    if (!this.isBlockDayAvailable(date)) return;
    if (!this.blockDateSelectionStarted || this.blockStartsOn === '') {
      this.blockStartsOn = date;
      this.blockEndsOn = date;
      this.blockDateSelectionStarted = true;
      return;
    }
    if (date === this.blockStartsOn) {
      this.blockStartsOn = '';
      this.blockEndsOn = '';
      this.blockDateSelectionStarted = false;
      return;
    }
    if (date === this.blockEndsOn) {
      this.blockEndsOn = this.blockStartsOn;
      return;
    }
    if (date < this.blockStartsOn) {
      this.blockStartsOn = date;
      this.blockEndsOn = date;
      return;
    }
    this.blockEndsOn = date;
  }

  protected onBlockScopeChange(): void {
    if (this.blockStartsOn === '') return;
    if (!this.isBlockDayAvailable(this.blockStartsOn)) {
      const firstDay = this.blockCalendarDays().find((day): day is BlockCalendarDay => day !== null && this.isBlockDayAvailable(day.value));
      if (firstDay) {
        this.blockStartsOn = firstDay.value;
        this.blockEndsOn = firstDay.value;
      }
    }
    this.blockDateSelectionStarted = false;
  }

  protected previousBlockMonth(): void {
    const previous = new Date(this.blockCalendarMonth());
    previous.setMonth(previous.getMonth() - 1);
    if (previous >= this.monthStart(new Date())) this.blockCalendarMonth.set(previous);
  }

  protected nextBlockMonth(): void {
    const next = new Date(this.blockCalendarMonth());
    next.setMonth(next.getMonth() + 1);
    this.blockCalendarMonth.set(next);
  }

  protected canGoToPreviousBlockMonth(): boolean {
    return this.blockCalendarMonth() > this.monthStart(new Date());
  }

  protected onBlockStartTimeChange(): void {
    if (this.blockStartsAt < this.blockEndsAt) return;
    const [hours, minutes] = this.blockStartsAt.split(':').map(Number);
    const endMinutes = Math.min(hours * 60 + minutes + 60, 23 * 60 + 59);
    this.blockEndsAt = `${String(Math.floor(endMinutes / 60)).padStart(2, '0')}:${String(endMinutes % 60).padStart(2, '0')}`;
  }

  protected async deleteBlock(block: AppointmentBlock): Promise<void> {
    if (!await this.dialogs.confirm('Sperrzeit wirklich löschen?', { title: 'Sperrzeit löschen', confirmLabel: 'Löschen', destructive: true })) return;
    try { await this.service.deleteBlock(block.id); await this.load(); this.message.set('Sperrzeit wurde gelöscht.'); }
    catch { this.error.set('Die Sperrzeit konnte nicht gelöscht werden.'); }
  }

  protected blockScope(block: AppointmentBlock): string { return block.typeId === null ? 'Gesamter Kalender' : this.typeNameFor(block.typeId); }

  protected blockPeriod(block: AppointmentBlock): string { return `${block.startsOn}${block.startsOn === block.endsOn ? '' : ` – ${block.endsOn}`}${block.startsAt && block.endsAt ? ` · ${block.startsAt}–${block.endsAt} Uhr` : ' · Ganztägig'}`; }

  protected formatDate(value: string): string {
    return new Intl.DateTimeFormat('de-AT', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Europe/Vienna',
    }).format(new Date(value));
  }

  protected weekdayLabel(weekday: number): string {
    return this.weekdays.find((day) => day.id === weekday)?.label ?? '–';
  }

  protected weekdayLabels(weekdays: readonly number[]): string {
    return weekdays.map((weekday) => this.weekdayLabel(weekday)).join(', ');
  }

  protected resourceNameFor(id: number): string {
    return this.resources().find((resource) => resource.id === id)?.name ?? 'Unbekannte Person';
  }

  protected typeNameFor(id: number): string {
    return this.types().find((type) => type.id === id)?.title ?? 'Unbekannte Terminart';
  }

  private async load(): Promise<void> {
    const request = ++this.appointmentsRequest;
    try {
      this.error.set('');
      const [data, cancellations, users] = await Promise.all([
        this.service.load(),
        this.service.listCustomerCancellations().catch(() => [...this.cancellationNotices()]),
        this.service.listStaffUsers().catch(() => [...this.staffUsers()]),
      ]);
      if (this.destroyed) return;
      if (request === this.appointmentsRequest) {
        this.appointments.set(data.appointments);
        this.applyCancellationNotices(cancellations);
        void this.markViewed(data.appointments);
      }
      this.types.set(data.types);
      this.resources.set(data.resources);
      this.staffUsers.set(users);
      this.availability.set(data.availability);
      this.blocks.set(data.blocks);
    } catch {
      if (!this.destroyed) this.error.set('Die Termindaten konnten nicht geladen werden.');
    } finally {
      if (!this.destroyed) this.loading.set(false);
    }
  }

  private async refreshAppointments(): Promise<void> {
    if (this.destroyed || document.hidden || this.polling) return;
    this.polling = true;
    const request = ++this.appointmentsRequest;
    try {
      const [appointments, cancellations] = await Promise.allSettled([
        this.service.listAppointments(), this.service.listCustomerCancellations(),
      ]);
      if (!this.destroyed && request === this.appointmentsRequest) {
        if (appointments.status === 'fulfilled') {
          this.appointments.set(appointments.value);
          void this.markViewed(appointments.value);
        }
        if (cancellations.status === 'fulfilled') this.applyCancellationNotices(cancellations.value);
      }
    } catch {
      // Keep the last successful list visible and retry on the next interval.
    } finally {
      this.polling = false;
    }
  }

  private applyCancellationNotices(items: readonly CustomerCancellationNotice[]): void {
    const next = items.filter((item) => item.id > this.dismissedCancellationId);
    if (next.map((item) => item.id).join(',') !== this.cancellationNotices().map((item) => item.id).join(',')) {
      this.cancellationNotices.set(next);
    }
  }

  private async markViewed(appointments: readonly AdminAppointment[]): Promise<void> {
    if (this.destroyed || document.hidden) return;
    const throughId = appointments.reduce((id, appointment) => Math.max(id, appointment.id), 0);
    if (throughId <= this.lastSeenInView) return;
    try {
      await this.service.markSeen(throughId);
      this.lastSeenInView = Math.max(this.lastSeenInView, throughId);
    } catch {
      // The next list refresh retries the read acknowledgement.
    }
  }

  private today(): string { return this.dateValue(new Date()); }

  private dateValue(date: Date): string {
    const offset = date.getTimezoneOffset() * 60_000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
  }

  private monthStart(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }
}
