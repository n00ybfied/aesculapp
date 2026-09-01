import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';

export interface PharmacyNewsPost {
  readonly id: number;
  readonly title: string;
  readonly subtitle: string;
  readonly bodyHtml: string;
  readonly imageUrl: string | null;
  readonly publishedAt: string;
}

@Injectable({ providedIn: 'root' })
export class PharmacyNewsService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = inject(API_BASE_URL);

  async getLatest(): Promise<readonly PharmacyNewsPost[]> {
    const response = await firstValueFrom(this.http.get<{ posts: PharmacyNewsPost[] }>(this.apiBaseUrl + '/news'));
    return response.posts;
  }

  async getOne(id: number): Promise<PharmacyNewsPost> {
    const response = await firstValueFrom(this.http.get<{ post: PharmacyNewsPost }>(this.apiBaseUrl + '/news/' + id));
    return response.post;
  }
}
