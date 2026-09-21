import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { firstValueFrom, tap } from 'rxjs';
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

  constructor() {
    if (!this.isAuthenticated()) {
      void this.restoreSession();
    }
  }

  readonly isAuthenticated = this.session.asReadonly();
  readonly accessToken = () => this.session()?.accessToken ?? '';
  readonly displayName = () => this.session()?.user.displayName ?? '';

  login(username: string, password: string) {
    return this.http
      .post<AdminLoginResponse>(`${this.apiBaseUrl()}/admin/auth/login`, { username, password }, { withCredentials: true })
      .pipe(tap((session) => this.storeSession(session)));
  }

  logout(): void {
    void firstValueFrom(this.http.post<void>(`${this.apiBaseUrl()}/admin/auth/logout`, {}, { withCredentials: true })).catch(() => undefined);
    this.clearSession();
  }

  async restoreSession(force = false): Promise<boolean> {
    if (!force && this.isAuthenticated()) {
      return true;
    }
    try {
      const session = await firstValueFrom(this.http.post<AdminLoginResponse>(`${this.apiBaseUrl()}/admin/auth/refresh`, {}, { withCredentials: true }));
      this.storeSession(session);
      return true;
    } catch {
      this.clearSession();
      return false;
    }
  }

  expireSession(): void {
    void this.restoreSession(true).then((restored) => {
      if (!restored) void this.router.navigateByUrl('/login');
    });
  }

  private clearSession(): void {
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

  private scheduleExpiry(delayMs: number): void { this.expiryTimer = setTimeout(() => this.expireSession(), delayMs); }
}
