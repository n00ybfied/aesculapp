import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface StaffAppointment {
  readonly id: number;
  readonly customer: string;
  readonly type: string;
  readonly startsAt: string;
  readonly endsAt: string;
  readonly status: 'pending_staff_confirmation' | 'reserved' | 'cancelled';
  readonly note: string | null;
}

export interface AppointmentAlert {
  readonly mode: 'confirmation' | 'new';
  readonly count: number;
}

@Injectable({ providedIn: 'root' })
export class StaffAppointmentService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  readonly alert = signal<AppointmentAlert | null>(null);
  private countLoading = false;
  private countRevision = 0;
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1/admin/appointments/mine'
    : 'https://api.aesculapp.floatbox.at/api/v1/admin/appointments/mine';

  async list(): Promise<StaffAppointment[]> {
    return (await firstValueFrom(this.http.get<{ appointments: StaffAppointment[] }>(this.api, this.options()))).appointments;
  }

  async confirm(id: number): Promise<StaffAppointment> {
    const appointment = (await firstValueFrom(this.http.post<{ appointment: StaffAppointment }>(`${this.api}/${id}/confirm`, {}, this.options()))).appointment;
    void this.refreshAlert();
    return appointment;
  }

  async markSeen(throughId: number): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api}/seen`, { throughId }, this.options()));
    await this.refreshAlert();
  }

  async refreshAlert(): Promise<void> {
    if (this.countLoading) return;
    this.countLoading = true;
    const revision = this.countRevision;
    try {
      const result = await firstValueFrom(this.http.get<AppointmentAlert>(`${this.api}/alert`, this.options()));
      if (revision === this.countRevision) this.alert.set(result);
    } catch {
      // Keep the last known count visible and retry during the next poll.
    } finally {
      this.countLoading = false;
    }
  }

  clearAlert(): void {
    this.countRevision += 1;
    this.alert.set(null);
  }

  private options() {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) };
  }
}
