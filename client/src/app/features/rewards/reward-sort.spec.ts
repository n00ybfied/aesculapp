import type { Reward } from '../../core/rewards/reward.repository';
import { sortRewards } from './reward-sort';

const rewards: Reward[] = [
  { id: '3', title: 'Zink', subtitle: '', description: '', requiredPoints: 500 },
  { id: '2', title: 'Apotheken-Tee', subtitle: '', description: '', requiredPoints: 200 },
  { id: '1', title: 'Balsam', subtitle: '', description: '', requiredPoints: 500 },
];

describe('sortRewards', () => {
  it('preserves the API order for newest first without changing the source list', () => {
    expect(sortRewards(rewards, 'newest').map((reward) => reward.id)).toEqual(['3', '2', '1']);
    expect(rewards.map((reward) => reward.id)).toEqual(['3', '2', '1']);
  });

  it('sorts by points and then by title for equal point costs', () => {
    expect(sortRewards(rewards, 'points-asc').map((reward) => reward.id)).toEqual(['2', '1', '3']);
    expect(sortRewards(rewards, 'points-desc').map((reward) => reward.id)).toEqual(['1', '3', '2']);
  });

  it('sorts names alphabetically', () => {
    expect(sortRewards(rewards, 'title-asc').map((reward) => reward.id)).toEqual(['2', '1', '3']);
  });
});
