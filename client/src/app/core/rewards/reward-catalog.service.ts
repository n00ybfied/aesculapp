import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import type { Reward } from './reward.repository';

interface RewardResponse {
  id: number;
  title: string;
  subtitle: string;
  description: string;
  imageUrl: string;
  requiredPoints: number;
}

@Injectable({ providedIn: 'root' })
export class RewardCatalogService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  async getVisibleRewards(): Promise<readonly Reward[]> {
    const response = await firstValueFrom(this.http.get<{ rewards: RewardResponse[] }>(`${this.apiBaseUrl}/rewards`));
    return response.rewards.map((reward) => ({
      id: String(reward.id),
      title: reward.title,
      subtitle: reward.subtitle,
      description: reward.description,
      imageUrl: reward.imageUrl,
      requiredPoints: reward.requiredPoints,
    }));
  }
}
