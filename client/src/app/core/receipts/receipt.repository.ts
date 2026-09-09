import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { ThemeService } from '../theme/theme.service';
import { parseCashRegisterQr } from './cash-register-qr.parser';

export interface ReceiptPreview {
  readonly id: string;
  readonly rawQrValue: string;
  readonly pharmacyName: string;
  readonly purchasedAt: string;
  readonly eligibleCents: number;
  readonly earnedPoints: number;
}

export interface ReceiptImportResult {
  readonly addedPoints: number;
}

export abstract class ReceiptRepository {
  abstract createPreview(rawQrValue: string): Promise<ReceiptPreview>;
  abstract import(receiptId: string): Promise<ReceiptImportResult>;
}

@Injectable()
export class MockReceiptRepository extends ReceiptRepository {
  private readonly theme = inject(ThemeService);
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);
  private readonly previews = new Map<string, ReceiptPreview>();

  async createPreview(rawQrValue: string): Promise<ReceiptPreview> {
    const receipt = parseCashRegisterQr(rawQrValue);
    const preview: ReceiptPreview = {
      id: `receipt-${receipt.receiptNumber}-${receipt.purchasedAt}`,
      rawQrValue,
      pharmacyName: 'Stadtapotheke Trofaiach',
      purchasedAt: new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(receipt.purchasedAt)),
      eligibleCents: receipt.eligibleCents,
      earnedPoints: Math.floor(receipt.eligibleCents * this.theme.pointsPerEuro / 100),
    };
    this.previews.set(preview.id, preview);
    return preview;
  }

  async import(receiptId: string): Promise<ReceiptImportResult> {
    const preview = this.previews.get(receiptId);
    if (!preview) {
      throw new Error('Receipt preview not found.');
    }

    const token = this.auth.accessToken();
    if (token === null) {
      throw new Error('Unauthorized.');
    }

    const result = await firstValueFrom(this.http.post<ReceiptImportResult>(
      `${this.apiBaseUrl}/receipts/import`,
      { rawQrValue: preview.rawQrValue },
      { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) },
    ));
    this.previews.delete(receiptId);
    return result;
  }
}
