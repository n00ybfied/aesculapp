import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface CustomerProfile {
  readonly id: number;
  readonly username: string;
  readonly email: string;
  readonly displayName: string;
  readonly phone: string | null;
  readonly streetAddress: string | null;
  readonly postalCode: string | null;
  readonly city: string | null;
  readonly profileImageUrl: string | null;
  readonly birthDate: string | null;
  readonly newsletterEnabled: boolean;
  readonly chatPushEnabled: boolean;
  readonly rewardPushEnabled: boolean;
  readonly newsPushEnabled: boolean;
  readonly medicationPushEnabled: boolean;
  readonly appointmentPushEnabled: boolean;
  readonly familyPushEnabled: boolean;
  readonly morningReminderTime: string;
  readonly noonReminderTime: string;
  readonly eveningReminderTime: string;
  readonly nightReminderTime: string;
  readonly footerNavigationItems: readonly FooterNavigationItem[];
}

export type FooterNavigationItem = 'home' | 'chat' | 'rewards' | 'coupons' | 'news' | 'appointments' | 'my-appointments' | 'medications' | 'family' | 'contact' | 'website';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);
  readonly profile = signal<CustomerProfile | null>(null);

  async load(): Promise<CustomerProfile> {
    const response = await firstValueFrom(this.http.get<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile', { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  async save(profile: Pick<CustomerProfile, 'username' | 'displayName' | 'phone' | 'streetAddress' | 'postalCode' | 'city' | 'birthDate' | 'newsletterEnabled' | 'chatPushEnabled' | 'rewardPushEnabled' | 'newsPushEnabled' | 'medicationPushEnabled' | 'appointmentPushEnabled' | 'familyPushEnabled' | 'morningReminderTime' | 'noonReminderTime' | 'eveningReminderTime' | 'nightReminderTime' | 'footerNavigationItems'> & { usernamePassword?: string }): Promise<CustomerProfile> {
    const response = await firstValueFrom(this.http.patch<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile', profile, { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  async uploadPhoto(photo: Blob): Promise<CustomerProfile> {
    const body = new FormData();
    body.append('photo', photo, 'profilbild.jpg');
    const response = await firstValueFrom(this.http.post<{ profile: CustomerProfile }>(this.apiBaseUrl + '/profile/photo', body, { headers: this.headers() }));
    this.profile.set(response.profile);
    return response.profile;
  }

  async requestEmailChange(email: string, password: string): Promise<string> {
    const response = await firstValueFrom(this.http.post<{ message: string }>(
      this.apiBaseUrl + '/profile/email-change/request',
      { email, password },
      { headers: this.headers() },
    ));
    return response.message;
  }

  async deleteAccount(password: string): Promise<void> {
    const userId = this.profile()?.id;
    await firstValueFrom(this.http.request<void>('DELETE', this.apiBaseUrl + '/profile', {
      body: { password },
      headers: this.headers(),
    }));
    this.profile.set(null);
    try {
      localStorage.removeItem('aesculapp.mock-rewards.v1');
      if (userId !== undefined) {
        localStorage.removeItem(`aesculapp.push-prompt.v1.${userId}`);
        localStorage.removeItem(`aesculapp.push-categories-hint.v1.${userId}`);
        const noticeKeys = Array.from({ length: localStorage.length }, (_, index) => localStorage.key(index))
          .filter((key): key is string => key !== null && key.startsWith('aesculapp.app-notice.v1.') && key.endsWith(`.${userId}`));
        for (const key of noticeKeys) localStorage.removeItem(key);
      }
    } catch {
      // Browser privacy settings can disable local storage; server deletion has succeeded.
    }
  }

  private headers(): HttpHeaders {
    const token = this.auth.accessToken();
    return token === null ? new HttpHeaders() : new HttpHeaders({ Authorization: 'Bearer ' + token });
  }
}
