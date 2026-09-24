import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin, interval, startWith, switchMap } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { AdminCustomerService } from '../../core/customers/admin-customer.service';
import { CustomerDetailsModalComponent } from '../../shared/customer-details-modal.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

interface CustomerSummary { id: number; displayName: string; username: string; profileImageUrl: string | null; }
interface ActiveRedemption { id: number; customer: string; customerDetails: CustomerSummary; summary: string; points: number; validUntil: string; }
interface ActiveCouponRedemption { id: string; customer: string; customerDetails: CustomerSummary; summary: string; items: { couponId: number; title: string }[]; validUntil: string; }

@Component({ selector: 'app-active-redemptions', imports: [CustomerDetailsModalComponent], templateUrl: './active-redemptions.component.html', styleUrl: './active-redemptions.component.css' })
export class ActiveRedemptionsComponent {
  private readonly http = inject(HttpClient); private readonly auth = inject(AdminAuthService); private readonly destroyRef = inject(DestroyRef);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly customers = inject(AdminCustomerService); protected readonly redemptions = signal<readonly ActiveRedemption[]>([]); protected readonly couponRedemptions = signal<readonly ActiveCouponRedemption[]>([]); protected readonly error = signal('');
  constructor() { interval(5_000).pipe(startWith(0), switchMap(() => forkJoin({
    rewards: this.http.get<{ redemptions: ActiveRedemption[] }>(this.api() + '/admin/redemptions/active', { headers: this.headers() }),
    coupons: this.http.get<{ redemptions: ActiveCouponRedemption[] }>(this.api() + '/admin/coupons/redemptions/active', { headers: this.headers() }),
  })), takeUntilDestroyed(this.destroyRef)).subscribe({ next: ({ rewards, coupons }) => { this.redemptions.set(rewards.redemptions); this.couponRedemptions.set(coupons.redemptions); this.error.set(''); }, error: () => this.error.set('Aktive Einlösungen konnten nicht geladen werden.') }); }
  protected async cancel(item: ActiveRedemption): Promise<void> { if (!await this.dialogs.confirm('Einlösung von ' + item.customerDetails.displayName + ' abbrechen und ' + item.points + ' Punkte zurückgeben?', { title: 'Einlösung abbrechen', confirmLabel: 'Abbrechen', destructive: true })) return; this.http.post(this.api() + '/admin/redemptions/' + item.id + '/cancel', {}, { headers: this.headers() }).subscribe({ next: () => this.redemptions.update((items) => items.filter((entry) => entry.id !== item.id)), error: () => this.error.set('Die Einlösung konnte nicht abgebrochen werden.') }); }
  protected async cancelCoupon(item: ActiveCouponRedemption): Promise<void> { if (!await this.dialogs.confirm('Gutschein-Einlösung von ' + item.customerDetails.displayName + ' abbrechen und den Gutschein wieder freigeben?', { title: 'Gutschein-Einlösung abbrechen', confirmLabel: 'Abbrechen', destructive: true })) return; this.http.post(this.api() + '/admin/coupons/redemptions/' + item.id + '/cancel', {}, { headers: this.headers() }).subscribe({ next: () => this.couponRedemptions.update((items) => items.filter((entry) => entry.id !== item.id)), error: () => this.error.set('Die Gutschein-Einlösung konnte nicht abgebrochen werden.') }); }
  protected secondsLeft(item: { validUntil: string }): number { return Math.max(0, Math.ceil((new Date(item.validUntil).getTime() - Date.now()) / 1000)); }
  protected openCustomer(id: number): void { this.customers.open(id); }
  protected initials(customer: CustomerSummary): string { return this.customers.initials(customer); }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
