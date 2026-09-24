import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminAppointment {
  id: number;
  customer: string;
  customerId: number | null;
  type: string;
  resource: string;
  resourceId: number;
  resourceColor: string;
  startsAt: string;
  endsAt: string;
  status: 'reserved' | 'cancelled' | 'pending_staff_confirmation';
  note: string | null;
  chatConversationId: number | null;
}

export interface AppointmentStaffUser { id: number; displayName: string; email: string; }

export interface AppointmentType {
  id: number;
  title: string;
  description: string | null;
  durationMinutes: number;
  bufferMinutes: number;
  isVisible: boolean;
}

export interface AppointmentResource {
  id: number;
  name: string;
  color: string;
  userId: number | null;
}

export interface CustomerCancellationNotice {
  id: number;
  appointmentId: number;
  customer: string;
  startsAt: string;
  occurredAt: string;
}

export interface AppointmentAvailability {
  id: number;
  resourceId: number;
  typeId: number;
  weekdays: number[];
  startsAt: string;
  endsAt: string;
}

export interface AppointmentBlock {
  id: number;
  typeId: number | null;
  startsOn: string;
  endsOn: string;
  startsAt: string | null;
  endsAt: string | null;
  comment: string | null;
}

@Injectable({ providedIn: 'root' })
export class AdminAppointmentService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  readonly unseenCount = signal<number | null>(null);
  private countPending = false;
  private countRevision = 0;
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1'
    : 'https://api.aesculapp.floatbox.at/api/v1';

  async load(): Promise<{
    appointments: AdminAppointment[];
    types: AppointmentType[];
    resources: AppointmentResource[];
    availability: AppointmentAvailability[];
    blocks: AppointmentBlock[];
  }> {
    const [appointments, types, resources, availability, blocks] = await Promise.all([
      this.listAppointments(),
      firstValueFrom(this.http.get<{ types: AppointmentType[] }>(`${this.api}/admin/appointments/types`, this.options())),
      firstValueFrom(this.http.get<{ resources: AppointmentResource[] }>(`${this.api}/admin/appointments/resources`, this.options())),
      firstValueFrom(this.http.get<{ availability: AppointmentAvailability[] }>(`${this.api}/admin/appointments/availability`, this.options())),
      firstValueFrom(this.http.get<{ blocks: AppointmentBlock[] }>(`${this.api}/admin/appointments/blocks`, this.options())),
    ]);

    return {
      appointments,
      types: types.types,
      resources: resources.resources,
      availability: availability.availability,
      blocks: blocks.blocks,
    };
  }

  async listAppointments(): Promise<AdminAppointment[]> {
    const result = await firstValueFrom(this.http.get<{ appointments: AdminAppointment[] }>(`${this.api}/admin/appointments`, this.options()));
    return result.appointments;
  }

  async listStaffUsers(): Promise<AppointmentStaffUser[]> {
    const result = await firstValueFrom(this.http.get<{ users: AppointmentStaffUser[] }>(`${this.api}/admin/appointments/staff-users`, this.options()));
    return result.users;
  }

  async listCustomerCancellations(): Promise<CustomerCancellationNotice[]> {
    const result = await firstValueFrom(this.http.get<{ cancellations: CustomerCancellationNotice[] }>(
      `${this.api}/admin/appointments/customer-cancellations`, this.options(),
    ));
    return result.cancellations;
  }

  acknowledgeCustomerCancellations(throughId: number): Promise<unknown> {
    return firstValueFrom(this.http.post(
      `${this.api}/admin/appointments/customer-cancellations/acknowledge`, { throughId }, this.options(),
    ));
  }

  createAppointment(data: {
    typeId: number;
    resourceId: number;
    date: string;
    time: string;
    customerId: number | null;
    guestName: string;
    note: string;
  }): Promise<{ appointment: AdminAppointment }> {
    return firstValueFrom(this.http.post<{ appointment: AdminAppointment }>(`${this.api}/admin/appointments`, data, this.options()));
  }

  async refreshUnseenCount(): Promise<void> {
    if (this.countPending) return;
    this.countPending = true;
    const revision = ++this.countRevision;
    try {
      const result = await firstValueFrom(this.http.get<{ count: number }>(`${this.api}/admin/appointments/unseen-count`, this.options()));
      if (revision === this.countRevision) this.unseenCount.set(result.count);
    } catch {
      if (revision === this.countRevision) this.unseenCount.set(null);
    } finally {
      this.countPending = false;
    }
  }

  async markSeen(throughId: number): Promise<void> {
    const revision = ++this.countRevision;
    const result = await firstValueFrom(this.http.post<{ count: number }>(`${this.api}/admin/appointments/seen`, { throughId }, this.options()));
    if (revision === this.countRevision) this.unseenCount.set(result.count);
  }

  createType(data: { title: string; durationMinutes: number; bufferMinutes: number }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/types`, data, this.options()));
  }

  updateType(id: number, data: { title: string; durationMinutes: number; bufferMinutes: number; isVisible: boolean }): Promise<unknown> {
    return firstValueFrom(this.http.patch(`${this.api}/admin/appointments/types/${id}`, data, this.options()));
  }

  createResource(data: { name: string; color: string; userId: number | null }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/resources`, data, this.options()));
  }

  updateResource(id: number, data: { name: string; color: string; userId: number | null; isActive: boolean }): Promise<unknown> {
    return firstValueFrom(this.http.patch(`${this.api}/admin/appointments/resources/${id}`, data, this.options()));
  }

  createAvailability(data: {
    resourceId: number;
    typeId: number;
    weekdays: number[];
    startsAt: string;
    endsAt: string;
  }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/availability`, data, this.options()));
  }

  updateAvailability(id: number, data: { resourceId: number; typeId: number; weekdays: number[]; startsAt: string; endsAt: string }): Promise<unknown> {
    return firstValueFrom(this.http.patch(`${this.api}/admin/appointments/availability/${id}`, data, this.options()));
  }

  cancel(id: number): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/${id}/cancel`, { notifyCustomer: true }, this.options()));
  }

  async confirm(id: number): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api}/admin/appointments/${id}/confirm`, {}, this.options()));
  }

  async listChats(id: number): Promise<{ conversations: { id: number; status: string; updatedAt: string }[] }> {
    return firstValueFrom(this.http.get<{ conversations: { id: number; status: string; updatedAt: string }[] }>(
      `${this.api}/admin/appointments/${id}/chats`, this.options(),
    ));
  }

  linkChat(id: number, conversationId: number | null): Promise<{ conversationId: number }> {
    return firstValueFrom(this.http.post<{ conversationId: number }>(
      `${this.api}/admin/appointments/${id}/chat`, { conversationId }, this.options(),
    ));
  }

  createBlock(data: { typeId: number | null; startsOn: string; endsOn: string; hasTime: boolean; startsAt: string; endsAt: string; comment: string }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/blocks`, data, this.options()));
  }

  updateBlock(id: number, data: { typeId: number | null; startsOn: string; endsOn: string; hasTime: boolean; startsAt: string; endsAt: string; comment: string }): Promise<unknown> {
    return firstValueFrom(this.http.patch(`${this.api}/admin/appointments/blocks/${id}`, data, this.options()));
  }

  deleteBlock(id: number): Promise<unknown> {
    return firstValueFrom(this.http.delete(`${this.api}/admin/appointments/blocks/${id}`, this.options()));
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) };
  }
}
