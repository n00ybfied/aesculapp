import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
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

export interface CustomerTransaction {
  readonly id: number;
  readonly points: number;
  readonly type: string;
  readonly label: string;
  readonly createdAt: string;
  readonly reversed: boolean;
  readonly canReverse: boolean;
}

interface CustomerDetailsResponse {
  readonly customer: AdminCustomer;
  readonly points: number;
  readonly transactions: readonly CustomerTransaction[];
}

export interface CustomerListItem {
  readonly id: number;
  readonly displayName: string;
  readonly email: string;
  readonly profileImageUrl: string | null;
  readonly points: number;
}

export interface CustomerListPage {
  readonly customers: readonly CustomerListItem[];
  readonly page: number;
  readonly total: number;
  readonly totalPages: number;
}

@Injectable({ providedIn: 'root' })
export class AdminCustomerService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  readonly customer = signal<AdminCustomer | null>(null);
  readonly points = signal(0);
  readonly transactions = signal<readonly CustomerTransaction[]>([]);
  readonly selectedCustomerId = signal<number | null>(null);
  readonly isOpen = signal(false);
  readonly isLoading = signal(false);
  readonly error = signal('');

  open(customerId: number): void {
    this.selectedCustomerId.set(customerId);
    this.isOpen.set(true);
    void this.load(customerId);
  }

  async reload(): Promise<void> {
    const customerId = this.selectedCustomerId();
    if (customerId !== null) {
      await this.load(customerId);
    }
  }

  async credit(customerId: number, points: number, label: string): Promise<void> {
    await firstValueFrom(this.http.post(this.api() + '/admin/customers/' + customerId + '/point-transactions', { points, label }, { headers: this.headers() }));
  }

  async reverse(customerId: number, transactionId: number): Promise<void> {
    await firstValueFrom(this.http.post(this.api() + '/admin/customers/' + customerId + '/point-transactions/' + transactionId + '/reverse', {}, { headers: this.headers() }));
  }

  private async load(customerId: number): Promise<void> {
    this.isLoading.set(true);
    this.error.set('');
    this.customer.set(null);
    this.transactions.set([]);
    try {
      const response = await firstValueFrom(this.http.get<CustomerDetailsResponse>(this.api() + '/admin/customers/' + customerId, { headers: this.headers() }));
      this.customer.set(response.customer);
      this.points.set(response.points);
      this.transactions.set(response.transactions);
    } catch {
      this.error.set('Kundendaten konnten nicht geladen werden.');
    } finally {
      this.isLoading.set(false);
    }
  }

  async list(query: string, page: number): Promise<CustomerListPage> {
    return firstValueFrom(this.http.get<CustomerListPage>(this.api() + '/admin/customers', {
      params: { query, page: String(page), pageSize: '20' },
      headers: this.headers(),
    }));
  }

  close(): void {
    this.isOpen.set(false);
    this.customer.set(null);
    this.transactions.set([]);
    this.selectedCustomerId.set(null);
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
