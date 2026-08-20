import { TestBed } from '@angular/core/testing';
import { MockRewardRepository, RewardRepository, rewardStorageKey } from '../rewards/reward.repository';
import { MockReceiptRepository } from './receipt.repository';

describe('MockReceiptRepository', () => {
  let repository: MockReceiptRepository;

  beforeEach(() => {
    localStorage.removeItem(rewardStorageKey);
    TestBed.configureTestingModule({
      providers: [MockReceiptRepository, MockRewardRepository, { provide: RewardRepository, useExisting: MockRewardRepository }],
    });
    repository = TestBed.inject(MockReceiptRepository);
  });

  it('credits the points after importing a receipt', async () => {
    await repository.import('receipt-00123456789');
    const rewards = TestBed.inject(RewardRepository);

    expect((await rewards.getOverview()).availablePoints).toBe(1_272);
  });

  it('creates a receipt preview from a scanned QR value', async () => {
    const preview = await repository.createPreview('STA|RECEIPT|2026-08-20|00123456789');

    expect(preview).toMatchObject({
      id: 'receipt-00123456789',
      purchasedAt: '2026-08-20',
      earnedPoints: 42,
    });
  });

  it('returns the credited points after importing a receipt', async () => {
    const result = await repository.import('receipt-00123456789');

    expect(result.addedPoints).toBe(42);
  });
});
