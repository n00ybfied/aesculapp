import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface ReferralOverview {
  readonly inviteUrl: string;
  readonly bonusPoints: number;
  readonly successfulInvitations: number;
}

@Injectable({ providedIn: 'root' })
export class ReferralService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  getOverview(): Promise<ReferralOverview> {
    return firstValueFrom(this.http.get<ReferralOverview>(`${this.apiBaseUrl}/referrals`, {
      headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }),
    }));
  }

  copyLink(url: string): Promise<void> {
    return navigator.clipboard.writeText(url);
  }

  canShare(): boolean {
    return typeof navigator.share === 'function';
  }

  shareLink(url: string): Promise<void> {
    return navigator.share({ title: 'Einladung zur Apotheke', text: 'Ich lade dich zur App unserer Apotheke ein.', url });
  }
}
