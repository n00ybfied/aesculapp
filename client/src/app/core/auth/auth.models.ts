export interface LoginCredentials {
  readonly email: string;
  readonly password: string;
}

export interface AuthUser {
  readonly id: number;
  readonly email: string;
  readonly displayName: string;
}

export interface LoginResponse {
  readonly accessToken: string;
  readonly tokenType: 'Bearer';
  readonly expiresIn: number;
  readonly user: AuthUser;
}

export interface RegistrationDetails {
  readonly displayName: string;
  readonly email: string;
  readonly password: string;
}

export type RegistrationResult = 'verification-required' | 'conflict' | 'invalid';
export type PasswordResetResult = 'success' | 'invalid';
