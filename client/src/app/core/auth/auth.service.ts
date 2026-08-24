import { computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import {
  AuthUser,
  LoginCredentials,
  LoginResponse,
  PasswordResetResult,
  RegistrationDetails,
  RegistrationResult,
} from './auth.models';

export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly currentUserState = signal<AuthUser | null>(null);
  private readonly accessTokenState = signal<string | null>(null);

  readonly currentUser = this.currentUserState.asReadonly();
  readonly accessToken = this.accessTokenState.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUserState() !== null);

  async login(credentials: LoginCredentials): Promise<boolean> {
    try {
      const response = await firstValueFrom(this.http.post<LoginResponse>(
        `${this.apiBaseUrl}/auth/login`,
        credentials,
      ));
      this.setAuthenticatedUser(response);
      return true;
    } catch {
      return false;
    }
  }

  async register(details: RegistrationDetails): Promise<RegistrationResult> {
    try {
      const response = await firstValueFrom(this.http.post<LoginResponse>(
        `${this.apiBaseUrl}/auth/register`,
        details,
      ));
      this.setAuthenticatedUser(response);
      return 'success';
    } catch (error: unknown) {
      return error instanceof HttpErrorResponse && error.status === 409 ? 'conflict' : 'invalid';
    }
  }

  async requestPasswordReset(email: string): Promise<boolean> {
    try {
      await firstValueFrom(this.http.post<void>(`${this.apiBaseUrl}/auth/password-reset/request`, { email }));
      return true;
    } catch {
      return false;
    }
  }

  async resetPassword(token: string, password: string): Promise<PasswordResetResult> {
    try {
      await firstValueFrom(this.http.post<void>(`${this.apiBaseUrl}/auth/password-reset/confirm`, { token, password }));
      return 'success';
    } catch {
      return 'invalid';
    }
  }

  logout(): void {
    this.accessTokenState.set(null);
    this.currentUserState.set(null);
  }

  private setAuthenticatedUser(response: LoginResponse): void {
    this.accessTokenState.set(response.accessToken);
    this.currentUserState.set(response.user);
  }
}
