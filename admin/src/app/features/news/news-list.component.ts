import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AdminNewsService, type NewsPage } from '../../core/news/admin-news.service';

@Component({ selector: 'app-news-list', imports: [RouterLink], templateUrl: './news-list.component.html', styleUrl: './news-list.component.css' })
export class NewsListComponent {
  private readonly newsService = inject(AdminNewsService);
  protected readonly newsPage = signal<NewsPage | null>(null);
  protected readonly error = signal('');
  protected readonly isLoading = signal(true);

  constructor() { void this.load(); }

  protected async load(page = 1): Promise<void> {
    this.isLoading.set(true); this.error.set('');
    try { this.newsPage.set(await this.newsService.list(page)); } catch { this.error.set('Beiträge konnten nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }
  protected async remove(id: number, title: string): Promise<void> {
    if (!confirm('„' + title + '“ wirklich löschen?')) { return; }
    try { await this.newsService.remove(id); await this.load(this.newsPage()?.page ?? 1); } catch { this.error.set('Beitrag konnte nicht gelöscht werden.'); }
  }
  protected formatDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium' }).format(new Date(value)); }
}
