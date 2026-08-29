import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';

interface AdminLoginResponse {
  accessToken: string;
  user: {
    displayName: string;
    username: string;
  };
}

@Injectable({ providedIn: 'root' })
export class AdminAuthService {
  private readonly http = inject(HttpClient);
  private readonly storageKey = 'aesculapp.admin.session';
  private readonly session = signal<AdminLoginResponse | null>(this.readSession());

  readonly isAuthenticated = this.session.asReadonly();
  readonly displayName = () => this.session()?.user.displayName ?? '';

  login(username: string, password: string) {
    return this.http
      .post<AdminLoginResponse>(`${this.apiBaseUrl()}/admin/auth/login`, { username, password })
      .pipe(tap((session) => this.storeSession(session)));
  }

  logout(): void {
    sessionStorage.removeItem(this.storageKey);
    this.session.set(null);
  }

  private apiBaseUrl(): string {
    const { hostname } = window.location;
    return hostname === 'localhost' || hostname === '127.0.0.1'
      ? 'http://localhost:6080/api/v1'
      : 'https://api.aesculapp.floatbox.at/api/v1';
  }

  private readSession(): AdminLoginResponse | null {
    const stored = sessionStorage.getItem(this.storageKey);
    if (null === stored) {
      return null;
    }

    try {
      return JSON.parse(stored) as AdminLoginResponse;
    } catch {
      sessionStorage.removeItem(this.storageKey);
      return null;
    }
  }

  private storeSession(session: AdminLoginResponse): void {
    sessionStorage.setItem(this.storageKey, JSON.stringify(session));
    this.session.set(session);
  }
}
