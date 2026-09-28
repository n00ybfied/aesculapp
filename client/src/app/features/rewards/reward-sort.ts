import type { Reward } from '../../core/rewards/reward.repository';

export type RewardSortOrder = 'newest' | 'points-asc' | 'points-desc' | 'title-asc';

export function sortRewards(rewards: readonly Reward[], order: RewardSortOrder): Reward[] {
  if (order === 'newest') {
    return [...rewards];
  }

  const byTitle = (left: Reward, right: Reward): number => left.title.localeCompare(right.title, 'de');
  return [...rewards].sort((left, right) => {
    if (order === 'title-asc') {
      return byTitle(left, right);
    }

    const pointDifference = left.requiredPoints - right.requiredPoints;
    return (order === 'points-asc' ? pointDifference : -pointDifference) || byTitle(left, right);
  });
}
