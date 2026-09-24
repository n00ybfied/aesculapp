import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, ElementRef, OnInit, inject, input, output, signal, viewChild } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime, distinctUntilChanged } from 'rxjs';
import { AdminCustomerService, type CustomerListItem } from '../../core/customers/admin-customer.service';
import { AdminAppointmentService, type AppointmentBlock, type AppointmentResource, type AppointmentType } from '../../core/appointments/admin-appointment.service';
import { blockedAppointment, pharmacyDateKey, pharmacyMinutes } from './appointment-calendar.layout';

@Component({
  selector: 'app-appointment-create',
  imports: [ReactiveFormsModule],
  templateUrl: './appointment-create.component.html',
  styleUrl: './appointment-create.component.css',
})
export class AppointmentCreateComponent implements OnInit {
  private readonly appointments = inject(AdminAppointmentService);
  private readonly customers = inject(AdminCustomerService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly firstField = viewChild<ElementRef<HTMLInputElement>>('firstField');
  private searchRevision = 0;

  readonly types = input.required<readonly AppointmentType[]>();
  readonly blocks = input.required<readonly AppointmentBlock[]>();
  readonly resources = input.required<readonly AppointmentResource[]>();
  readonly initialDate = input<string | null>(null);
  readonly initialTime = input<string | null>(null);
  readonly created = output<void>();
  readonly dismissed = output<void>();

  protected readonly form = new FormGroup({
    customerMode: new FormControl<'account' | 'guest'>('account', { nonNullable: true }),
    customerId: new FormControl<number | null>(null),
    guestName: new FormControl('', { nonNullable: true }),
    typeId: new FormControl('', { nonNullable: true }),
    resourceId: new FormControl('', { nonNullable: true }),
    date: new FormControl(pharmacyDateKey(new Date(Date.now() + 86_400_000)), { nonNullable: true }),
    time: new FormControl('09:00', { nonNullable: true }),
    note: new FormControl('', { nonNullable: true }),
  });
  protected readonly search = new FormControl('', { nonNullable: true });
  protected readonly matches = signal<readonly CustomerListItem[]>([]);
  protected readonly selectedCustomer = signal<CustomerListItem | null>(null);
  protected readonly searching = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal('');

  constructor() {
    this.search.valueChanges.pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(this.destroyRef))
      .subscribe((query) => void this.searchCustomers(query));
    setTimeout(() => this.firstField()?.nativeElement.focus());
  }

  ngOnInit(): void {
    const date = this.initialDate();
    const time = this.initialTime();
    if (date !== null) this.form.controls.date.setValue(date);
    if (time !== null) this.form.controls.time.setValue(time);
  }

  protected setMode(mode: 'account' | 'guest'): void {
    this.form.controls.customerMode.setValue(mode);
    this.error.set('');
    ++this.searchRevision;
    this.matches.set([]);
    this.searching.set(false);
  }

  protected selectCustomer(customer: CustomerListItem): void {
    this.selectedCustomer.set(customer);
    this.form.controls.customerId.setValue(customer.id);
    this.search.setValue(customer.displayName, { emitEvent: false });
    this.matches.set([]);
  }

  protected clearCustomer(): void {
    this.selectedCustomer.set(null);
    this.form.controls.customerId.setValue(null);
    this.search.setValue('');
  }

  protected onSearchInput(): void {
    if (this.selectedCustomer() !== null) {
      this.selectedCustomer.set(null);
      this.form.controls.customerId.setValue(null);
    }
  }

  protected blockedPeriod(): boolean {
    const { date, time, typeId } = this.form.getRawValue();
    const type = this.types().find((item) => item.id === Number(typeId));
    return type !== undefined && blockedAppointment(this.blocks(), date, time, type.durationMinutes, type.id);
  }

  protected pastPeriod(): boolean {
    const { date, time } = this.form.getRawValue();
    const now = new Date();
    const today = pharmacyDateKey(now);
    return date < today || (date === today && time <= this.timeLabel(pharmacyMinutes(now)));
  }

  protected async save(): Promise<void> {
    if (this.saving()) return;
    const value = this.form.getRawValue();
    if (!value.typeId || !value.resourceId || !value.date || !value.time
      || (value.customerMode === 'account' && value.customerId === null)
      || (value.customerMode === 'guest' && !value.guestName.trim())) {
      this.error.set('Bitte Terminart, Person, Zeitpunkt und einen Kunden oder Gastnamen angeben.');
      return;
    }
    if (this.pastPeriod()) {
      this.error.set('Termine können nur in der Zukunft angelegt werden.');
      return;
    }
    if (this.blockedPeriod()) {
      this.error.set('Dieser Zeitraum ist für die gewählte Terminart gesperrt.');
      return;
    }

    this.saving.set(true);
    this.error.set('');
    try {
      await this.appointments.createAppointment({
        typeId: Number(value.typeId),
        resourceId: Number(value.resourceId),
        date: value.date,
        time: value.time,
        customerId: value.customerMode === 'account' ? value.customerId : null,
        guestName: value.customerMode === 'guest' ? value.guestName.trim() : '',
        note: value.note.trim(),
      });
      this.created.emit();
    } catch (cause) {
      this.error.set(cause instanceof HttpErrorResponse && typeof cause.error?.message === 'string'
        ? cause.error.message : 'Der Termin konnte nicht angelegt werden.');
    } finally {
      this.saving.set(false);
    }
  }

  private timeLabel(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
  }

  private async searchCustomers(query: string): Promise<void> {
    const revision = ++this.searchRevision;
    const trimmed = query.trim();
    if (this.form.controls.customerMode.value !== 'account' || trimmed.length < 2 || this.selectedCustomer() !== null) {
      this.matches.set([]);
      this.searching.set(false);
      return;
    }
    this.searching.set(true);
    try {
      const page = await this.customers.list(trimmed, 1);
      if (revision === this.searchRevision) this.matches.set(page.customers);
    } catch {
      if (revision === this.searchRevision) this.error.set('Die Kundensuche ist momentan nicht verfügbar.');
    } finally {
      if (revision === this.searchRevision) this.searching.set(false);
    }
  }
}
