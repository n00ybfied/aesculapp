import { Component, inject } from '@angular/core';
import { AdminCustomerService } from '../core/customers/admin-customer.service';

@Component({
  selector: 'app-customer-details-modal',
  templateUrl: './customer-details-modal.component.html',
  styleUrl: './customer-details-modal.component.css',
})
export class CustomerDetailsModalComponent {
  protected readonly customers = inject(AdminCustomerService);
}
