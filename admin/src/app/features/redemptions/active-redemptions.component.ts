import { Component, inject } from '@angular/core';
import { AdminCustomerService } from '../../core/customers/admin-customer.service';
import {
  AdminRedemptionService,
  type ActiveCouponRedemption,
  type ActiveRewardRedemption,
  type RedemptionCustomerSummary,
} from '../../core/redemptions/admin-redemption.service';
import { CustomerDetailsModalComponent } from '../../shared/customer-details-modal.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

@Component({
  selector: 'app-active-redemptions',
  imports: [CustomerDetailsModalComponent],
  templateUrl: './active-redemptions.component.html',
  styleUrl: './active-redemptions.component.css',
})
export class ActiveRedemptionsComponent {
  private readonly redemptionsService = inject(AdminRedemptionService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly customers = inject(AdminCustomerService);
  protected readonly redemptions = this.redemptionsService.rewards;
  protected readonly couponRedemptions = this.redemptionsService.coupons;
  protected readonly error = this.redemptionsService.error;

  constructor() {
    void this.redemptionsService.refresh();
  }

  protected async cancel(item: ActiveRewardRedemption): Promise<void> {
    if (!await this.dialogs.confirm(`Einlösung von ${item.customerDetails.displayName} abbrechen und ${item.points} Punkte zurückgeben?`, {
      title: 'Einlösung abbrechen', confirmLabel: 'Abbrechen', destructive: true,
    })) return;
    try {
      await this.redemptionsService.cancelReward(item.id);
    } catch {
      this.error.set('Die Einlösung konnte nicht abgebrochen werden.');
    }
  }

  protected async cancelCoupon(item: ActiveCouponRedemption): Promise<void> {
    if (!await this.dialogs.confirm(`Gutschein-Einlösung von ${item.customerDetails.displayName} abbrechen und den Gutschein wieder freigeben?`, {
      title: 'Gutschein-Einlösung abbrechen', confirmLabel: 'Abbrechen', destructive: true,
    })) return;
    try {
      await this.redemptionsService.cancelCoupon(item.id);
    } catch {
      this.error.set('Die Gutschein-Einlösung konnte nicht abgebrochen werden.');
    }
  }

  protected secondsLeft(item: { validUntil: string }): number {
    return Math.max(0, Math.ceil((new Date(item.validUntil).getTime() - Date.now()) / 1_000));
  }

  protected openCustomer(id: number): void { this.customers.open(id); }
  protected initials(customer: RedemptionCustomerSummary): string { return this.customers.initials(customer); }
}
