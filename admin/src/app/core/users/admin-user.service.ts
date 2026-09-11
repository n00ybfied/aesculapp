import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export type StaffRole = 'staff' | 'admin';

export interface AdminUserSummary {
  readonly id: number;
  readonly displayName: string;
  readonly email: string;
  readonly roles: readonly string[];
}

export interface PendingInvitation {
  readonly id: number;
  readonly displayName: string;
  readonly email: string;
  readonly roles: readonly string[];
  readonly createdAt: string;
}

export interface AdminUsersOverview {
  readonly users: readonly AdminUserSummary[];
  readonly invitations: readonly PendingInvitation[];
}

export interface InvitationAcceptanceResult {
  readonly existingAccount: boolean;
}

@Injectable({ providedIn: 'root' })
export class AdminUserService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);

  async getOverview(): Promise<AdminUsersOverview> {
    return firstValueFrom(this.http.get<AdminUsersOverview>(`${this.api()}/admin/users`, { headers: this.headers() }));
  }

  async invite(displayName: string, email: string, role: StaffRole): Promise<void> {
    await firstValueFrom(this.http.post(`${this.api()}/admin/users/invitations`, { displayName, email, role }, { headers: this.headers() }));
  }

  async acceptInvitation(token: string, password: string): Promise<InvitationAcceptanceResult> {
    return firstValueFrom(this.http.post<InvitationAcceptanceResult>(`${this.api()}/admin/invitations/accept`, { token, password }));
  }

  private headers(): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` });
  }

  private api(): string {
    return location.hostname === 'localhost' || location.hostname === '127.0.0.1'
      ? 'http://localhost:6080/api/v1'
      : 'https://api.aesculapp.floatbox.at/api/v1';
  }
}
