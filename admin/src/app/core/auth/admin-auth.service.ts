import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { Router } from '@angular/router';

interface AdminLoginResponse {
  accessToken: string;
  expiresIn: number;
  user: {
    displayName: string;
    username: string;
  };
}

@Injectable({ providedIn: 'root' })
export class AdminAuthService {
  private readonly http = inject(HttpClient);
  private readonly storageKey = 'aesculapp.admin.session';
  private readonly router = inject(Router);
  private expiryTimer: ReturnType<typeof setTimeout> | undefined;
  private readonly session = signal<AdminLoginResponse | null>(this.readSession());

  readonly isAuthenticated = this.session.asReadonly();
  readonly accessToken = () => this.session()?.accessToken ?? '';
  readonly displayName = () => this.session()?.user.displayName ?? '';

  login(username: string, password: string) {
    return this.http
      .post<AdminLoginResponse>(`${this.apiBaseUrl()}/admin/auth/login`, { username, password })
      .pipe(tap((session) => this.storeSession(session)));
  }

  logout(): void {
    if (this.expiryTimer !== undefined) {
      clearTimeout(this.expiryTimer);
      this.expiryTimer = undefined;
    }
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
      const session = JSON.parse(stored) as AdminLoginResponse & { expiresAt?: number };
      if (typeof session.expiresAt !== 'number' || session.expiresAt <= Date.now()) {
        sessionStorage.removeItem(this.storageKey);
        return null;
      }
      this.scheduleExpiry(session.expiresAt - Date.now());
      return session;
    } catch {
      sessionStorage.removeItem(this.storageKey);
      return null;
    }
  }

  private storeSession(session: AdminLoginResponse): void {
    const expiresAt = Date.now() + session.expiresIn * 1_000;
    const storedSession = { ...session, expiresAt };
    sessionStorage.setItem(this.storageKey, JSON.stringify(storedSession));
    this.session.set(storedSession);
    this.scheduleExpiry(session.expiresIn * 1_000);
  }

  expireSession(): void { this.logout(); void this.router.navigateByUrl('/login'); }
  private scheduleExpiry(delayMs: number): void { this.expiryTimer = setTimeout(() => this.expireSession(), delayMs); }
}
