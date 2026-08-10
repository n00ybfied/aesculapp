export interface LoginCredentials {
  readonly username: string;
  readonly password: string;
}

export interface AuthUser {
  readonly id: string;
  readonly username: string;
  readonly displayName: string;
}
