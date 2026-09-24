import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminNewsService, type NewsPage } from '../../core/news/admin-news.service';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

@Component({ selector: 'app-news-list', imports: [FormsModule, RouterLink], templateUrl: './news-list.component.html', styleUrl: './news-list.component.css' })
export class NewsListComponent {
  private readonly newsService = inject(AdminNewsService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly newsPage = signal<NewsPage | null>(null);
  protected readonly error = signal('');
  protected readonly isLoading = signal(true);
  protected query = '';
  protected pageSize: 10 | 25 | 50 | 'all' = 25;

  constructor() { void this.load(); }

  protected async load(page = 1): Promise<void> {
    this.isLoading.set(true); this.error.set('');
    try { this.newsPage.set(await this.newsService.list(page, this.pageSize, this.query.trim())); } catch { this.error.set('Beiträge konnten nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }
  protected applyFilters(): void { void this.load(); }
  protected resetFilters(): void {
    this.query = '';
    this.pageSize = 25;
    void this.load(1);
  }
  protected updatePageSize(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.pageSize = value === 'all' ? 'all' : Number(value) as 10 | 25 | 50;
    void this.load();
  }
  protected async remove(id: number, title: string): Promise<void> {
    if (!await this.dialogs.confirm('„' + title + '“ wirklich löschen?', { title: 'Nachricht löschen', confirmLabel: 'Löschen', destructive: true })) { return; }
    try { await this.newsService.remove(id); await this.load(this.newsPage()?.page ?? 1); } catch { this.error.set('Beitrag konnte nicht gelöscht werden.'); }
  }
  protected formatDate(value: string): string { return new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium' }).format(new Date(value)); }
}
