import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { PharmacyNewsService, type PharmacyNewsPost } from '../../core/news/pharmacy-news.service';

@Component({ selector: 'app-news-list-page', imports: [RouterLink], templateUrl: './news-list.page.html' })
export class NewsListPage {
  private readonly newsService = inject(PharmacyNewsService);
  protected readonly posts = signal<readonly PharmacyNewsPost[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly page = signal(1);
  protected readonly totalPages = signal(1);

  constructor() { void this.load(); }

  protected formatDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium' }).format(new Date(value)); }
  protected changePage(page: number): void { if (page >= 1 && page <= this.totalPages() && page !== this.page()) void this.load(page); }

  private async load(page = 1): Promise<void> {
    this.isLoading.set(true);
    try { const response = await this.newsService.getPage(page); this.posts.set(response.posts); this.page.set(response.page); this.totalPages.set(response.totalPages); } finally { this.isLoading.set(false); }
  }
}
