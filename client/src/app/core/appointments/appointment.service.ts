import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface AppointmentType {
  readonly id: number;
  readonly title: string;
  readonly description: string | null;
  readonly durationMinutes: number;
}

export interface AppointmentTypesResponse {
  readonly types: AppointmentType[];
  readonly bookingWindowDays: number;
}

export interface AppointmentSlot {
  readonly resourceId: number;
  readonly resourceName: string | null;
  readonly requiresConfirmation: boolean;
  readonly startsAt: string;
  readonly endsAt: string;
}

export interface AppointmentBlock {
  readonly id: number;
  readonly startsOn: string;
  readonly endsOn: string;
  readonly startsAt: string | null;
  readonly endsAt: string | null;
  readonly comment: string | null;
}

export interface CustomerAppointment {
  readonly id: number;
  readonly type: string;
  readonly resource: string | null;
  readonly startsAt: string;
  readonly endsAt: string;
  readonly status: 'reserved' | 'pending_staff_confirmation' | 'cancelled';
}

@Injectable({ providedIn: 'root' })
export class AppointmentService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  async getTypes(): Promise<AppointmentTypesResponse> {
    return await firstValueFrom(
      this.http.get<{ types: AppointmentType[] }>(`${this.apiBaseUrl}/appointments/types`, this.options()),
    ) as AppointmentTypesResponse;
  }

  async getSlots(typeId: number, date: string): Promise<AppointmentSlot[]> {
    return (await firstValueFrom(
      this.http.get<{ slots: AppointmentSlot[] }>(`${this.apiBaseUrl}/appointments/slots`, {
        ...this.options(),
        params: { typeId: String(typeId), date },
      }),
    )).slots;
  }

  async getBlocks(typeId: number): Promise<AppointmentBlock[]> {
    return (await firstValueFrom(
      this.http.get<{ blocks: AppointmentBlock[] }>(`${this.apiBaseUrl}/appointments/blocks`, {
        ...this.options(),
        params: { typeId: String(typeId) },
      }),
    )).blocks;
  }

  async getMine(): Promise<CustomerAppointment[]> {
    return (await firstValueFrom(
      this.http.get<{ appointments: CustomerAppointment[] }>(`${this.apiBaseUrl}/appointments/mine`, this.options()),
    )).appointments;
  }

  book(typeId: number, startsAt: string, note: string): Promise<CustomerAppointment> {
    return firstValueFrom(
      this.http.post<{ appointment: CustomerAppointment }>(
        `${this.apiBaseUrl}/appointments`,
        { typeId, startsAt, note },
        this.options(),
      ),
    ).then((response) => response.appointment);
  }

  cancel(id: number): Promise<CustomerAppointment> {
    return firstValueFrom(
      this.http.post<{ appointment: CustomerAppointment }>(`${this.apiBaseUrl}/appointments/${id}/cancel`, {}, this.options()),
    ).then((response) => response.appointment);
  }

  private options(): { headers: HttpHeaders } {
    return {
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }),
    };
  }
}
