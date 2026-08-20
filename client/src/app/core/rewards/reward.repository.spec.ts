import { TestBed } from '@angular/core/testing';
import { MockRewardRepository, rewardStorageKey } from './reward.repository';

describe('MockRewardRepository', () => {
  let repository: MockRewardRepository;

  beforeEach(() => {
    localStorage.removeItem(rewardStorageKey);
    TestBed.configureTestingModule({ providers: [MockRewardRepository] });
    repository = TestBed.inject(MockRewardRepository);
  });

  it('keeps the points in Local Storage', async () => {
    await repository.credit(42, 'Testgutschrift');

    const reloadedRepository = new MockRewardRepository();
    const overview = await reloadedRepository.getOverview();

    expect(overview.availablePoints).toBe(1_272);
  });

  it('redeems multiple quantities of an available reward and subtracts its points', async () => {
    const redemption = await repository.redeem([{ rewardId: 'tea', quantity: 2 }]);
    const overview = await repository.getOverview();

    expect(redemption.remainingPoints).toBe(230);
    expect(redemption.activeRedemption.items).toEqual([{
      rewardId: 'tea',
      title: 'Tee-Genuss',
      quantity: 2,
      pointsPerItem: 500,
    }]);
    expect(overview.history[0]).toMatchObject({ label: '2× Tee-Genuss', points: -1_000 });
  });

  it('adds further rewards to an active redemption and persists it', async () => {
    await repository.credit(1_000, 'Testgutschrift');
    await repository.redeem([{ rewardId: 'tea', quantity: 1 }]);
    const redemption = await repository.redeem([{ rewardId: 'tea', quantity: 2 }]);
    const reloadedRepository = new MockRewardRepository();

    expect(redemption.remainingPoints).toBe(730);
    expect(redemption.activeRedemption.items[0].quantity).toBe(3);
    expect(await reloadedRepository.getActiveRedemption()).toEqual(redemption.activeRedemption);
  });

  it('does not change the point balance when the selection is too expensive', async () => {
    let error: unknown;
    try {
      await repository.redeem([{ rewardId: 'tea', quantity: 3 }]);
    } catch (caught) {
      error = caught;
    }

    expect(error).toBeDefined();
    expect((await repository.getOverview()).availablePoints).toBe(1_230);
  });
});
