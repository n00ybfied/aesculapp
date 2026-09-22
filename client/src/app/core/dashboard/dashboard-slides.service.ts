import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface DashboardSlide {
  readonly id: number;
  readonly imageUrl: string;
  readonly linkUrl: string | null;
  readonly position: number;
}

export interface DashboardSliderSettings {
  readonly transition: 'fade' | 'slide';
  readonly animationDurationMs: number;
  readonly delayMs: number;
  readonly autoplay: boolean;
}

export interface DashboardSlidesData {
  readonly slides: readonly DashboardSlide[];
  readonly settings: DashboardSliderSettings;
}

@Injectable({ providedIn: 'root' })
export class DashboardSlidesService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  async list(): Promise<DashboardSlidesData> {
    return await firstValueFrom(this.http.get<DashboardSlidesData>(`${this.apiBaseUrl}/dashboard/slides`, {
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }),
    }));
  }
}
