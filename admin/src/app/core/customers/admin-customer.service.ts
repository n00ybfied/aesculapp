import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminCustomer {
  readonly id: number;
  readonly username: string;
  readonly displayName: string;
  readonly email: string;
  readonly phone: string | null;
  readonly streetAddress: string | null;
  readonly postalCode: string | null;
  readonly city: string | null;
  readonly profileImageUrl: string | null;
}

@Injectable({ providedIn: 'root' })
export class AdminCustomerService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  readonly customer = signal<AdminCustomer | null>(null);
  readonly isOpen = signal(false);
  readonly isLoading = signal(false);
  readonly error = signal('');

  open(customerId: number): void {
    this.isOpen.set(true);
    this.isLoading.set(true);
    this.error.set('');
    this.customer.set(null);
    this.http.get<{ customer: AdminCustomer }>(this.api() + '/admin/customers/' + customerId, { headers: this.headers() }).subscribe({
      next: ({ customer }) => { this.customer.set(customer); this.isLoading.set(false); },
      error: () => { this.error.set('Kundendaten konnten nicht geladen werden.'); this.isLoading.set(false); },
    });
  }

  close(): void {
    this.isOpen.set(false);
    this.customer.set(null);
    this.error.set('');
  }

  initials(customer: Pick<AdminCustomer, 'displayName'>): string {
    return customer.displayName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'K';
  }

  private headers(): HttpHeaders {
    return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() });
  }

  private api(): string {
    return location.hostname === 'localhost' || location.hostname === '127.0.0.1'
      ? 'http://localhost:6080/api/v1'
      : 'https://api.aesculapp.floatbox.at/api/v1';
  }
}
