import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminChange {
  readonly id: number;
  readonly actorUserId: number;
  readonly actorName: string;
  readonly entityType: string;
  readonly entityId: string | null;
  readonly action: 'created' | 'updated' | 'deleted';
  readonly occurredAt: string;
}

export interface AdminChangePage {
  readonly changes: readonly AdminChange[];
  readonly hasMore: boolean;
}

@Injectable({ providedIn: 'root' })
export class AdminAuditService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);

  async list(entityType: string, entityId?: number | string | null, requestPath?: string, before?: number): Promise<AdminChangePage> {
    let params = new HttpParams().set('entityType', entityType);
    if (entityId !== null && entityId !== undefined) params = params.set('entityId', String(entityId));
    if (requestPath) params = params.set('requestPath', requestPath);
    if (before) params = params.set('before', before);
    const base = ['localhost', '127.0.0.1'].includes(location.hostname)
      ? 'http://localhost:6080/api/v1'
      : 'https://api.aesculapp.floatbox.at/api/v1';
    return firstValueFrom(this.http.get<AdminChangePage>(`${base}/admin/audit`, {
      params,
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }),
    }));
  }
}
