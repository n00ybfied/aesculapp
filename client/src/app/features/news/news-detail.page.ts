import { Component, ViewEncapsulation, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { PharmacyNewsService, type PharmacyNewsPost } from '../../core/news/pharmacy-news.service';

@Component({ selector: 'app-news-detail-page', imports: [RouterLink], templateUrl: './news-detail.page.html', styleUrl: './news-detail.page.css', encapsulation: ViewEncapsulation.None })
export class NewsDetailPage {
  private readonly route = inject(ActivatedRoute);
  private readonly newsService = inject(PharmacyNewsService);
  protected readonly post = signal<PharmacyNewsPost | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly notFound = signal(false);

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!Number.isInteger(id) || id <= 0) { this.notFound.set(true); this.isLoading.set(false); return; }
    void this.load(id);
  }

  protected formatDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { dateStyle: 'long' }).format(new Date(value)); }
  private async load(id: number): Promise<void> {
    try { this.post.set(await this.newsService.getOne(id)); } catch { this.notFound.set(true); } finally { this.isLoading.set(false); }
  }
}
