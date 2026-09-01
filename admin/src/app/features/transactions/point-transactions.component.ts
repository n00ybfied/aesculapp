import { DatePipe } from '@angular/common';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { AdminCustomerService } from '../../core/customers/admin-customer.service';
import { CustomerDetailsModalComponent } from '../../shared/customer-details-modal.component';

interface CustomerSummary { id: number; username: string; displayName: string; profileImageUrl: string | null; }
interface Transaction { id: number; points: number; type: string; label: string; createdAt: string; customer: CustomerSummary; }

@Component({ selector: 'app-point-transactions', imports: [DatePipe, FormsModule, CustomerDetailsModalComponent], templateUrl: './point-transactions.component.html', styleUrl: './point-transactions.component.css' })
export class PointTransactionsComponent {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  protected readonly customers = inject(AdminCustomerService);
  protected readonly transactions = signal<readonly Transaction[]>([]);
  protected readonly error = signal('');
  protected query = '';
  protected activeOnly = false;
  constructor() { this.search(); }
  protected search(): void {
    const params = { query: this.query, activeOnly: String(this.activeOnly) };
    this.http.get<{ transactions: Transaction[] }>(this.api() + '/admin/point-transactions', { params, headers: this.headers() }).subscribe({ next: ({ transactions }) => { this.transactions.set(transactions); this.error.set(''); }, error: () => this.error.set('Buchungen konnten nicht geladen werden.') });
  }
  protected openCustomer(id: number): void { this.customers.open(id); }
  protected initials(customer: CustomerSummary): string { return this.customers.initials(customer); }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
