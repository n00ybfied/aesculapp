import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface FamilyConnection {
  readonly id: number;
  readonly status: 'pending' | 'accepted';
  readonly isIncomingInvitation: boolean;
  readonly pointSharingStatus: 'none' | 'pending' | 'accepted';
  readonly isPointSharingRequestedByCurrentUser: boolean;
  readonly canAcceptPointSharing: boolean;
  readonly other: { readonly id: number; readonly displayName: string; readonly profileImageUrl: string | null };
  readonly canViewMedication: boolean;
  readonly canManageMedication: boolean;
  readonly otherCanViewMedication: boolean;
  readonly otherCanManageMedication: boolean;
}

@Injectable({ providedIn: 'root' })
export class FamilyService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly api = inject(API_BASE_URL) + '/family';
  readonly connections = signal<readonly FamilyConnection[]>([]);

  async load(): Promise<readonly FamilyConnection[]> { const response = await firstValueFrom(this.http.get<{ connections: FamilyConnection[] }>(this.api, this.options())); this.connections.set(response.connections); return response.connections; }
  async invite(email: string): Promise<void> { await firstValueFrom(this.http.post(this.api + '/invitations', { email }, this.options())); await this.load(); }
  async accept(token: string): Promise<void> { await firstValueFrom(this.http.post(this.api + '/invitations/accept', { token }, this.options())); await this.load(); }
  async acceptListedInvitation(id: number): Promise<void> { await firstValueFrom(this.http.post(`${this.api}/${id}/accept`, {}, this.options())); await this.load(); }
  async requestPointSharing(id: number): Promise<void> { const response = await firstValueFrom(this.http.post<{ connection: FamilyConnection }>(`${this.api}/${id}/point-sharing/request`, {}, this.options())); this.replace(response.connection); }
  async acceptPointSharing(id: number): Promise<void> { const response = await firstValueFrom(this.http.post<{ connection: FamilyConnection }>(`${this.api}/${id}/point-sharing/accept`, {}, this.options())); this.replace(response.connection); }
  async updateMedicationAccess(id: number, allowView: boolean, allowManage: boolean): Promise<void> { await firstValueFrom(this.http.patch(`${this.api}/${id}/medication-access`, { allowView, allowManage }, this.options())); await this.load(); }
  async disconnect(id: number): Promise<void> { await firstValueFrom(this.http.delete(`${this.api}/${id}`, this.options())); await this.load(); }
  private replace(connection: FamilyConnection): void { this.connections.update((connections) => connections.map((item) => item.id === connection.id ? connection : item)); }
  private options(): { headers: HttpHeaders } { return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }) }; }
}
