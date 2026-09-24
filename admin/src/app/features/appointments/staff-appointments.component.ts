import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { StaffAppointment, StaffAppointmentService } from '../../core/appointments/staff-appointment.service';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

@Component({
  imports: [],
  template: `
    <section class="page">
      <p class="eyebrow">TERMINE</p>
      <h2>Meine Termine</h2>
      <p class="intro">Hier sehen Sie die Termine, die Ihnen zugeteilt wurden.</p>
      @if (error()) { <p class="error" role="alert">{{ error() }}</p> }
      @if (message()) { <p class="message" role="status">{{ message() }}</p> }
      @if (loading()) { <p>Termine werden geladen …</p> }
      @else {
        @if (pending().length) {
          <section aria-labelledby="pending-heading">
            <h3 id="pending-heading">Warten auf Ihre Bestätigung</h3>
            @for (appointment of pending(); track appointment.id) {
              <article class="card">
                <div><strong>{{ appointment.customer }} · {{ appointment.type }}</strong><p>{{ format(appointment.startsAt) }}</p>@if (appointment.note) { <p>{{ appointment.note }}</p> }</div>
                <button type="button" [disabled]="confirming() === appointment.id" (click)="confirm(appointment)">{{ confirming() === appointment.id ? 'Bestätigt …' : 'Termin bestätigen' }}</button>
              </article>
            }
          </section>
        }
        <section aria-labelledby="upcoming-heading">
          <h3 id="upcoming-heading">Bevorstehende Termine</h3>
          @for (appointment of upcoming(); track appointment.id) {
            <article class="card"><div><strong>{{ appointment.customer }} · {{ appointment.type }}</strong><p>{{ format(appointment.startsAt) }}</p>@if (appointment.note) { <p>{{ appointment.note }}</p> }</div><span>{{ appointment.status === 'cancelled' ? 'Abgesagt' : 'Bestätigt' }}</span></article>
          } @empty { <p class="empty">Keine bevorstehenden Termine.</p> }
        </section>
      }
    </section>
  `,
  styles: [`
    .page{max-width:60rem}.eyebrow{color:var(--admin-primary);font-size:.8rem;font-weight:700;letter-spacing:.08em}.intro,.empty{color:var(--admin-muted)}
    .card{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:.7rem 0;padding:1rem 1.2rem;border:1px solid var(--admin-border);border-radius:.8rem;background:var(--admin-surface)}
    .card p{margin:.35rem 0 0;color:var(--admin-muted)}button{min-height:2.75rem;padding:.5rem .9rem;border:0;border-radius:.5rem;background:var(--admin-primary);color:#fff;font-weight:700;cursor:pointer}button:disabled{opacity:.55}.error{color:var(--admin-danger)}.message{color:var(--admin-primary-strong)}
    @media(max-width:650px){.card{align-items:stretch;flex-direction:column}.card button{width:100%}}
  `],
})
export class StaffAppointmentsComponent implements OnInit, OnDestroy {
  private readonly service = inject(StaffAppointmentService);
  private readonly auth = inject(AdminAuthService);
  private timer: ReturnType<typeof setInterval> | null = null;
  private destroyed = false;
  private refreshing = false;
  private lastSeenInView = -1;
  protected readonly appointments = signal<readonly StaffAppointment[]>([]);
  protected readonly pending = computed(() => this.appointments().filter((item) => item.status === 'pending_staff_confirmation' && new Date(item.startsAt).getTime() > Date.now()));
  protected readonly upcoming = computed(() => this.appointments().filter((item) => item.status !== 'pending_staff_confirmation'));
  protected readonly loading = signal(true);
  protected readonly confirming = signal<number | null>(null);
  protected readonly message = signal('');
  protected readonly error = signal('');

  async ngOnInit(): Promise<void> {
    await this.refresh();
    this.timer = setInterval(() => { if (!document.hidden) void this.refresh(); }, 5_000);
  }

  ngOnDestroy(): void {
    this.destroyed = true;
    if (this.timer) clearInterval(this.timer);
  }

  protected format(value: string): string {
    return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
  }

  protected async confirm(appointment: StaffAppointment): Promise<void> {
    if (this.confirming() !== null) return;
    this.confirming.set(appointment.id);
    this.error.set('');
    try {
      await this.service.confirm(appointment.id);
      this.message.set('Termin wurde bestätigt.');
      await this.refresh();
    } catch {
      this.error.set('Der Termin konnte nicht bestätigt werden. Bitte laden Sie die Liste neu.');
    } finally {
      this.confirming.set(null);
    }
  }

  private async refresh(): Promise<void> {
    if (this.refreshing) return;
    this.refreshing = true;
    try {
      const appointments = await this.service.list();
      if (!this.destroyed) {
        this.appointments.set(appointments);
        const throughId = appointments.reduce((id, appointment) => Math.max(id, appointment.id), 0);
        if (!this.auth.isTenantAdmin() && throughId > this.lastSeenInView) {
          try {
            await this.service.markSeen(throughId);
            this.lastSeenInView = throughId;
          } catch {
            // The next visible refresh retries the acknowledgement.
          }
        }
      }
    } catch {
      if (!this.destroyed) this.error.set('Die Termine konnten nicht geladen werden.');
    } finally {
      this.refreshing = false;
      if (!this.destroyed) this.loading.set(false);
    }
  }
}
