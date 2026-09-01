import { Component, DOCUMENT, ElementRef, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminNewsService } from '../../core/news/admin-news.service';

@Component({ selector: 'app-news-editor', imports: [FormsModule, RouterLink], templateUrl: './news-editor.component.html', styleUrl: './news-editor.component.css' })
export class NewsEditorComponent {
  private readonly newsService = inject(AdminNewsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly document = inject(DOCUMENT);
  private readonly editor = viewChild<ElementRef<HTMLElement>>('editor');
  private postId: number | null = null;
  private image: File | null = null;
  protected readonly isLoading = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly imagePreviewUrl = signal<string | null>(null);
  protected readonly removeExistingImage = signal(false);
  protected title = '';
  protected subtitle = '';
  protected publishedAt = this.toInputValue(new Date().toISOString());
  protected showFrom = '';
  protected showUntil = '';
  protected isVisible = true;

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (Number.isInteger(id) && id > 0) { this.postId = id; void this.load(id); }
  }

  protected get isEdit(): boolean { return this.postId !== null; }
  protected run(command: string): void { this.editor()?.nativeElement.focus(); this.document.execCommand(command); }
  protected createLink(): void { const url = prompt('Link-Adresse (https://… oder mailto:…)'); if (url) { this.document.execCommand('createLink', false, url); } }
  protected async insertContentImage(event: Event): Promise<void> {
    const image = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (!image) { return; }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(image.type) || image.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'); return; }
    this.error.set('');
    try { const imageUrl = await this.newsService.uploadContentImage(image); this.editor()?.nativeElement.focus(); this.document.execCommand('insertImage', false, imageUrl); } catch { this.error.set('Das Bild konnte nicht hochgeladen werden.'); } finally { (event.target as HTMLInputElement).value = ''; }
  }
  protected selectImage(event: Event): void { this.setImage((event.target as HTMLInputElement).files?.[0] ?? null); }
  protected dragOver(event: DragEvent): void { event.preventDefault(); }
  protected dropImage(event: DragEvent): void { event.preventDefault(); this.setImage(event.dataTransfer?.files.item(0) ?? null); }
  protected removeImage(): void { this.image = null; this.imagePreviewUrl.set(null); this.removeExistingImage.set(true); }

  protected async save(): Promise<void> {
    const bodyHtml = this.editor()?.nativeElement.innerHTML.trim() ?? '';
    if (!this.title.trim() || !this.subtitle.trim() || !bodyHtml) { this.error.set('Bitte füllen Sie alle Pflichtfelder aus.'); return; }
    const data = new FormData();
    data.set('title', this.title.trim()); data.set('subtitle', this.subtitle.trim()); data.set('bodyHtml', bodyHtml); data.set('publishedAt', this.publishedAt); data.set('showFrom', this.showFrom); data.set('showUntil', this.showUntil); data.set('isVisible', String(this.isVisible)); data.set('removeImage', String(this.removeExistingImage()));
    if (this.image) { data.set('image', this.image); }
    this.isSaving.set(true); this.error.set('');
    try { if (this.postId === null) { await this.newsService.create(data); } else { await this.newsService.update(this.postId, data); } await this.router.navigateByUrl('/inhalte'); } catch { this.error.set('Der Beitrag konnte nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
  }

  private async load(id: number): Promise<void> {
    this.isLoading.set(true);
    try { const post = await this.newsService.get(id); this.title = post.title; this.subtitle = post.subtitle; this.publishedAt = this.toInputValue(post.publishedAt); this.showFrom = post.showFrom ? this.toInputValue(post.showFrom) : ''; this.showUntil = post.showUntil ? this.toInputValue(post.showUntil) : ''; this.isVisible = post.isVisible; this.imagePreviewUrl.set(post.imageUrl); queueMicrotask(() => { const target = this.editor()?.nativeElement; if (target) { target.innerHTML = post.bodyHtml; } }); } catch { this.error.set('Der Beitrag konnte nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }
  private setImage(file: File | null): void { if (!file) { return; } if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'); return; } this.image = file; this.removeExistingImage.set(false); this.error.set(''); this.imagePreviewUrl.set(URL.createObjectURL(file)); }
  private toInputValue(value: string): string { const date = new Date(value); const offset = date.getTimezoneOffset() * 60000; return new Date(date.getTime() - offset).toISOString().slice(0, 16); }
}
