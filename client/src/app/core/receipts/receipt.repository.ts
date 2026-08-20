import { Injectable } from '@angular/core';
import { RewardRepository } from '../rewards/reward.repository';

export interface ReceiptPreview {
  readonly id: string;
  readonly rawQrValue: string;
  readonly pharmacyName: string;
  readonly purchasedAt: string;
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
  constructor(private readonly rewards: RewardRepository) {
    super();
  }

  async createPreview(rawQrValue: string): Promise<ReceiptPreview> {
    const [, , purchasedAt = '2026-08-20', receiptNumber = '00123456789'] = rawQrValue.split('|');

    return {
      id: `receipt-${receiptNumber}`,
      rawQrValue,
      pharmacyName: 'Stadtapotheke Trofaiach',
      purchasedAt,
      earnedPoints: 42,
    };
  }

  async import(_receiptId: string): Promise<ReceiptImportResult> {
    await this.rewards.credit(42, 'Einkauf in der Stadtapotheke');
    return { addedPoints: 42 };
  }
}
