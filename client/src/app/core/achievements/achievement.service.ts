import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface Achievement {
  readonly id: string;
  readonly title: string;
  readonly description: string;
  readonly points: number;
  readonly completed: boolean;
  readonly progress: number;
  readonly target: number;
  readonly missingFields: readonly string[];
  readonly actionPath: string | null;
}

@Injectable({ providedIn: 'root' })
export class AchievementService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  async list(): Promise<readonly Achievement[]> {
    const token = this.auth.accessToken();
    const headers = token === null ? new HttpHeaders() : new HttpHeaders({ Authorization: `Bearer ${token}` });
    const response = await firstValueFrom(this.http.get<{ achievements: readonly Achievement[] }>(`${this.apiBaseUrl}/achievements`, { headers }));
    return response.achievements;
  }
}
