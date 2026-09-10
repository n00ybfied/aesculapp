import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminCustomerService, type CustomerListPage, type CustomerListItem } from '../../core/customers/admin-customer.service';
import { CustomerDetailsModalComponent } from '../../shared/customer-details-modal.component';

@Component({
  selector: 'app-admin-customers',
  imports: [FormsModule, CustomerDetailsModalComponent],
  templateUrl: './admin-customers.component.html',
  styleUrl: './admin-customers.component.css',
})
export class AdminCustomersComponent {
  protected readonly customers = inject(AdminCustomerService);
  protected readonly results = signal<CustomerListPage | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected query = '';

  constructor() {
    void this.load(1);
  }

  protected search(): void {
    void this.load(1);
  }

  protected goToPage(page: number): void {
    void this.load(page);
  }

  protected initials(customer: CustomerListItem): string {
    return this.customers.initials(customer);
  }

  private async load(page: number): Promise<void> {
    this.isLoading.set(true);
    try {
      this.results.set(await this.customers.list(this.query.trim(), page));
      this.error.set(null);
    } catch {
      this.error.set('Kunden konnten nicht geladen werden.');
    } finally {
      this.isLoading.set(false);
    }
  }
}
