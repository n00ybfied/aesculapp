import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminAppointment {
  id: number;
  customer: string;
  type: string;
  resource: string;
  startsAt: string;
  endsAt: string;
  status: 'reserved' | 'cancelled';
}

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
      firstValueFrom(this.http.get<{ appointments: AdminAppointment[] }>(`${this.api}/admin/appointments`, this.options())),
      firstValueFrom(this.http.get<{ types: AppointmentType[] }>(`${this.api}/admin/appointments/types`, this.options())),
      firstValueFrom(this.http.get<{ resources: AppointmentResource[] }>(`${this.api}/admin/appointments/resources`, this.options())),
      firstValueFrom(this.http.get<{ availability: AppointmentAvailability[] }>(`${this.api}/admin/appointments/availability`, this.options())),
      firstValueFrom(this.http.get<{ blocks: AppointmentBlock[] }>(`${this.api}/admin/appointments/blocks`, this.options())),
    ]);

    return {
      appointments: appointments.appointments,
      types: types.types,
      resources: resources.resources,
      availability: availability.availability,
      blocks: blocks.blocks,
    };
  }

  createType(data: { title: string; durationMinutes: number; bufferMinutes: number }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/types`, data, this.options()));
  }

  updateType(id: number, data: { title: string; durationMinutes: number; bufferMinutes: number; isVisible: boolean }): Promise<unknown> {
    return firstValueFrom(this.http.patch(`${this.api}/admin/appointments/types/${id}`, data, this.options()));
  }

  createResource(data: { name: string }): Promise<unknown> {
    return firstValueFrom(this.http.post(`${this.api}/admin/appointments/resources`, data, this.options()));
  }

  updateResource(id: number, data: { name: string; isActive: boolean }): Promise<unknown> {
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
