import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';
import { RewardCatalogService } from './reward-catalog.service';
import { MockRewardRepository, rewardStorageKey, type Reward, type RewardsOverview } from './reward.repository';

describe('MockRewardRepository', () => {
  const api = 'http://api.test/api/v1';
  const tea: Reward = { id: 'tea', title: 'Tee-Genuss', subtitle: 'Wohlfühltee', description: 'Testprämie', requiredPoints: 500 };
  let repository: MockRewardRepository;
  let httpTesting: HttpTestingController;

  beforeEach(() => {
    localStorage.removeItem(rewardStorageKey);
    TestBed.configureTestingModule({ providers: [
      MockRewardRepository,
      provideHttpClient(),
      provideHttpClientTesting(),
      { provide: API_BASE_URL, useValue: api },
      { provide: AuthService, useValue: { accessToken: () => null } },
      { provide: RewardCatalogService, useValue: { getVisibleRewards: async () => [tea] } },
    ] });
    repository = TestBed.inject(MockRewardRepository);
    httpTesting = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpTesting.verify());

  async function loadCatalog(target = repository): Promise<RewardsOverview> {
    const loading = target.getOverview();
    httpTesting.expectOne(`${api}/rewards/active`).flush(null, { status: 503, statusText: 'Service Unavailable' });
    return loading;
  }

  async function completeRedemption(quantity: number, remainingPoints: number, redemptionId: number) {
    const redemption = repository.redeem([{ rewardId: 'tea', quantity }]);
    const request = httpTesting.expectOne(`${api}/rewards/redeem`);
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ selections: [{ rewardId: 'tea', quantity }] });
    request.flush({ remainingPoints, redemption: { id: redemptionId, validUntil: new Date(Date.now() + 300_000).toISOString() } });
    await Promise.resolve();
    httpTesting.expectOne(`${api}/rewards/active`).flush(null, { status: 503, statusText: 'Service Unavailable' });
    return redemption;
  }

  it('keeps credited points in Local Storage', async () => {
    await repository.credit(42, 'Testgutschrift');

    const reloadedRepository = TestBed.runInInjectionContext(() => new MockRewardRepository());
    const overview = await loadCatalog(reloadedRepository);

    expect(overview.availablePoints).toBe(1_272);
  });

  it('redeems multiple quantities and subtracts their points', async () => {
    await loadCatalog();
    const redemption = await completeRedemption(2, 230, 1);

    expect(redemption.remainingPoints).toBe(230);
    expect(redemption.activeRedemption.items).toEqual([{
      rewardId: 'tea', title: 'Tee-Genuss', subtitle: 'Wohlfühltee', imageUrl: undefined,
      quantity: 2, pointsPerItem: 500,
    }]);
    const stored = JSON.parse(localStorage.getItem(rewardStorageKey) ?? 'null') as { history: Array<{ label: string; points: number }> };
    expect(stored.history[0]).toMatchObject({ label: '2× Tee-Genuss', points: -1_000 });
  });

  it('adds further rewards to an active redemption and persists it', async () => {
    await loadCatalog();
    await repository.credit(1_000, 'Testgutschrift');
    await completeRedemption(1, 1_730, 1);
    const redemption = await completeRedemption(2, 730, 1);
    const reloadedRepository = TestBed.runInInjectionContext(() => new MockRewardRepository());
    const active = reloadedRepository.getActiveRedemption();
    httpTesting.expectOne(`${api}/rewards/active`).flush(null, { status: 503, statusText: 'Service Unavailable' });

    expect(redemption.remainingPoints).toBe(730);
    expect(redemption.activeRedemption.items[0].quantity).toBe(3);
    expect(await active).toEqual(redemption.activeRedemption);
  });

  it('does not change the balance when a selection is too expensive', async () => {
    await loadCatalog();

    await expect(repository.redeem([{ rewardId: 'tea', quantity: 3 }])).rejects.toThrow();
    const stored = JSON.parse(localStorage.getItem(rewardStorageKey) ?? 'null') as { availablePoints: number };
    expect(stored.availablePoints).toBe(1_230);
  });
});
