import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminNewsPost {
  readonly id: number;
  readonly title: string;
  readonly subtitle: string;
  readonly bodyHtml: string;
  readonly imageUrl: string | null;
  readonly isVisible: boolean;
  readonly publishedAt: string;
  readonly showFrom: string | null;
  readonly showUntil: string | null;
  readonly categoryIds: readonly number[];
  readonly notificationSalutations: readonly ('frau' | 'herr' | 'divers')[];
}

export interface NewsCategory { readonly id: number; readonly name: string; }

export interface NewsPage {
  readonly posts: readonly AdminNewsPost[];
  readonly page: number;
  readonly total: number;
  readonly totalPages: number;
}

@Injectable({ providedIn: 'root' })
export class AdminNewsService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);

  async list(page = 1, pageSize: 10 | 25 | 50 | 'all' = 25, query = ''): Promise<NewsPage> {
    return firstValueFrom(this.http.get<NewsPage>(this.api() + '/admin/news', {
      params: { page: String(page), pageSize: String(pageSize), query },
      headers: this.headers(),
    }));
  }

  async get(id: number): Promise<AdminNewsPost> {
    const response = await firstValueFrom(this.http.get<{ post: AdminNewsPost }>(this.api() + '/admin/news/' + id, { headers: this.headers() }));
    return response.post;
  }

  async create(data: FormData): Promise<AdminNewsPost> {
    const response = await firstValueFrom(this.http.post<{ post: AdminNewsPost }>(this.api() + '/admin/news', data, { headers: this.headers() }));
    return response.post;
  }

  async update(id: number, data: FormData): Promise<AdminNewsPost> {
    const response = await firstValueFrom(this.http.post<{ post: AdminNewsPost }>(this.api() + '/admin/news/' + id, data, { headers: this.headers() }));
    return response.post;
  }

  async uploadContentImage(image: File): Promise<string> {
    const data = new FormData();
    data.set('image', image);
    const response = await firstValueFrom(this.http.post<{ imageUrl: string }>(this.api() + '/admin/news/images', data, { headers: this.headers() }));
    return response.imageUrl;
  }

  async remove(id: number): Promise<void> {
    await firstValueFrom(this.http.delete<void>(this.api() + '/admin/news/' + id, { headers: this.headers() }));
  }

  async categories(): Promise<readonly NewsCategory[]> {
    const response = await firstValueFrom(this.http.get<{ categories: NewsCategory[] }>(this.api() + '/admin/news/categories', { headers: this.headers() }));
    return response.categories;
  }

  async createCategory(name: string): Promise<void> {
    await firstValueFrom(this.http.post(this.api() + '/admin/news/categories', { name }, { headers: this.headers() }));
  }

  async renameCategory(id: number, name: string): Promise<void> {
    await firstValueFrom(this.http.patch(this.api() + '/admin/news/categories/' + id, { name }, { headers: this.headers() }));
  }

  async removeCategory(id: number): Promise<void> {
    await firstValueFrom(this.http.delete(this.api() + '/admin/news/categories/' + id, { headers: this.headers() }));
  }

  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
