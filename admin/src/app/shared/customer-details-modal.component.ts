import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AdminCustomerService } from '../core/customers/admin-customer.service';
import { ConfirmDialogService } from './confirm-dialog.service';
import { AdminChangeHistoryComponent } from './admin-change-history.component';

@Component({
  selector: 'app-customer-details-modal',
  imports: [DatePipe, FormsModule, AdminChangeHistoryComponent],
  templateUrl: './customer-details-modal.component.html',
  styleUrl: './customer-details-modal.component.css',
})
export class CustomerDetailsModalComponent {
  protected readonly customers = inject(AdminCustomerService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly actionError = signal('');
  protected readonly isSaving = signal(false);
  protected readonly auditRefresh = signal(0);
  protected pointsToCredit: number | null = null;
  protected creditReason = '';

  protected async credit(customerId: number): Promise<void> {
    const points = this.pointsToCredit;
    if (points === null || !Number.isInteger(points) || points < 1 || this.creditReason.trim().length < 3) {
      this.actionError.set('Bitte geben Sie mindestens 1 Punkt und einen kurzen Grund an.');
      return;
    }

    this.isSaving.set(true);
    this.actionError.set('');
    try {
      await this.customers.credit(customerId, points, this.creditReason.trim());
      this.pointsToCredit = null;
      this.creditReason = '';
      await this.customers.reload();
      this.auditRefresh.update((value) => value + 1);
    } catch {
      this.actionError.set('Die Aufladung konnte nicht gespeichert werden.');
    } finally {
      this.isSaving.set(false);
    }
  }

  protected async reverse(customerId: number, transactionId: number): Promise<void> {
    if (!await this.dialogs.confirm('Diese Buchung wird durch eine Gegenbuchung storniert. Fortfahren?', { title: 'Buchung stornieren', confirmLabel: 'Stornieren', destructive: true })) {
      return;
    }

    this.isSaving.set(true);
    this.actionError.set('');
    try {
      await this.customers.reverse(customerId, transactionId);
      await this.customers.reload();
    } catch {
      this.actionError.set('Die Buchung konnte nicht storniert werden. Aktive Einlösungen müssen in der Einlösungsübersicht abgebrochen werden.');
    } finally {
      this.isSaving.set(false);
    }
  }
}
