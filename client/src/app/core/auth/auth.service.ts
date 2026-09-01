import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthUser, LoginCredentials, LoginResponse, PasswordResetResult, RegistrationDetails, RegistrationResult } from './auth.models';

export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly router = inject(Router);
  private readonly currentUserState = signal<AuthUser | null>(null);
  private readonly accessTokenState = signal<string | null>(null);
  private expiryTimer: ReturnType<typeof setTimeout> | undefined;
  readonly currentUser = this.currentUserState.asReadonly();
  readonly accessToken = this.accessTokenState.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUserState() !== null);

  async login(credentials: LoginCredentials): Promise<boolean> {
    try { this.setAuthenticatedUser(await firstValueFrom(this.http.post<LoginResponse>(this.apiBaseUrl + '/auth/login', credentials, { withCredentials: true }))); return true; } catch { return false; }
  }
  async register(details: RegistrationDetails): Promise<RegistrationResult> {
    try { this.setAuthenticatedUser(await firstValueFrom(this.http.post<LoginResponse>(this.apiBaseUrl + '/auth/register', details, { withCredentials: true }))); return 'success'; } catch (error: unknown) { return error instanceof HttpErrorResponse && error.status === 409 ? 'conflict' : 'invalid'; }
  }
  async requestPasswordReset(email: string): Promise<boolean> {
    try { await firstValueFrom(this.http.post<void>(this.apiBaseUrl + '/auth/password-reset/request', { email })); return true; } catch { return false; }
  }
  async resetPassword(token: string, password: string): Promise<PasswordResetResult> {
    try { await firstValueFrom(this.http.post<void>(this.apiBaseUrl + '/auth/password-reset/confirm', { token, password })); return 'success'; } catch { return 'invalid'; }
  }
  logout(): void {
    void firstValueFrom(this.http.post<void>(this.apiBaseUrl + '/auth/logout', {}, { withCredentials: true })).catch(() => undefined);
    this.clearSession();
  }
  async restoreSession(): Promise<boolean> {
    try {
      this.setAuthenticatedUser(await firstValueFrom(this.http.post<LoginResponse>(this.apiBaseUrl + '/auth/refresh', {}, { withCredentials: true })));
      return true;
    } catch {
      this.clearSession();
      return false;
    }
  }
  expireSession(): void {
    void this.restoreSession().then((restored) => { if (!restored) void this.router.navigateByUrl('/login'); });
  }
  private setAuthenticatedUser(response: LoginResponse): void {
    this.clearSession();
    this.accessTokenState.set(response.accessToken);
    this.currentUserState.set(response.user);
    this.expiryTimer = setTimeout(() => this.expireSession(), response.expiresIn * 1_000);
  }
  private clearSession(): void {
    if (this.expiryTimer !== undefined) { clearTimeout(this.expiryTimer); this.expiryTimer = undefined; }
    this.accessTokenState.set(null);
    this.currentUserState.set(null);
  }
}
