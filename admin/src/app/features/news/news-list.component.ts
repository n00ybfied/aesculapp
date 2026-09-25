import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminNewsService, type NewsCategory, type NewsPage } from '../../core/news/admin-news.service';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

@Component({ selector: 'app-news-list', imports: [FormsModule, RouterLink], templateUrl: './news-list.component.html', styleUrl: './news-list.component.css' })
export class NewsListComponent {
  private readonly newsService = inject(AdminNewsService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly newsPage = signal<NewsPage | null>(null);
  protected readonly categories = signal<readonly NewsCategory[]>([]);
  protected categoryName = '';
  protected readonly editingCategoryId = signal<number | null>(null);
  protected editingCategoryName = '';
  protected readonly error = signal('');
  protected readonly isLoading = signal(true);
  protected query = '';
  protected pageSize: 10 | 25 | 50 | 'all' = 25;

  constructor() { void this.load(); void this.loadCategories(); }

  private async loadCategories(): Promise<void> { try { this.categories.set(await this.newsService.categories()); } catch { this.error.set('Kategorien konnten nicht geladen werden.'); } }
  protected async createCategory(): Promise<void> {
    if (!this.categoryName.trim()) return;
    try { await this.newsService.createCategory(this.categoryName.trim()); this.categoryName = ''; await this.loadCategories(); } catch { this.error.set('Kategorie konnte nicht angelegt werden. Prüfen Sie, ob der Name bereits existiert.'); }
  }
  protected startCategoryRename(category: NewsCategory): void { this.editingCategoryId.set(category.id); this.editingCategoryName = category.name; }
  protected async renameCategory(category: NewsCategory): Promise<void> {
    const name = this.editingCategoryName.trim();
    if (!name) { this.error.set('Bitte einen neuen Kategorienamen eingeben.'); return; }
    try { await this.newsService.renameCategory(category.id, name); this.editingCategoryId.set(null); await this.loadCategories(); } catch { this.error.set('Kategorie konnte nicht umbenannt werden.'); }
  }
  protected async removeCategory(category: NewsCategory): Promise<void> {
    if (!await this.dialogs.confirm('Kategorie „' + category.name + '“ löschen? Sie wird auch aus Beiträgen und Kundeninteressen entfernt.', { title: 'Kategorie löschen', confirmLabel: 'Löschen', destructive: true })) return;
    try { await this.newsService.removeCategory(category.id); await this.loadCategories(); } catch { this.error.set('Kategorie konnte nicht gelöscht werden.'); }
  }

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
