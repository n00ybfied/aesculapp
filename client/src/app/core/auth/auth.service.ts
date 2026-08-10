import { computed, signal } from '@angular/core';
import { AuthUser, LoginCredentials } from './auth.models';

const MOCK_USER: AuthUser & { readonly password: string } = {
  id: 'customer-001',
  username: 'kunde',
  displayName: 'Kunde',
  password: 'trofaiach',
};

export class AuthService {
  private readonly currentUserState = signal<AuthUser | null>(null);

  readonly currentUser = this.currentUserState.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUserState() !== null);

  login(credentials: LoginCredentials): boolean {
    const isValid =
      credentials.username.trim().toLocaleLowerCase('de') === MOCK_USER.username &&
      credentials.password === MOCK_USER.password;

    if (!isValid) {
      return false;
    }

    const { password: _password, ...user } = MOCK_USER;
    this.currentUserState.set(user);
    return true;
  }

  logout(): void {
    this.currentUserState.set(null);
  }
}
