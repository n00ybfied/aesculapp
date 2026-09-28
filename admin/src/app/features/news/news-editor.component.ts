import { Component, inject, signal } from '@angular/core';
import { RichTextEditorComponent } from '../../shared/rich-text-editor.component';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminNewsService, type NewsCategory } from '../../core/news/admin-news.service';
import { MediaPickerComponent, type PickedMedia } from '../../shared/media-picker.component';
import { AdminChangeHistoryComponent } from '../../shared/admin-change-history.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

@Component({ selector: 'app-news-editor', imports: [FormsModule, RouterLink, MediaPickerComponent, RichTextEditorComponent, AdminChangeHistoryComponent], templateUrl: './news-editor.component.html', styleUrl: './news-editor.component.css' })
export class NewsEditorComponent {
  private readonly news = inject(AdminNewsService); private readonly route = inject(ActivatedRoute); private readonly router = inject(Router);
  private readonly dialogs = inject(ConfirmDialogService);
  protected postId: number | null = null; private image: File | null = null; protected bodyHtml = '';
  private selectedMediaPath: string | null = null;
  protected readonly isLoading = signal(false); protected readonly isSaving = signal(false); protected readonly error = signal(''); protected readonly imagePreviewUrl = signal<string | null>(null); protected readonly removeExistingImage = signal(false); protected readonly editorState = signal(0);
  protected readonly categories = signal<readonly NewsCategory[]>([]);
  protected categoryIds: number[] = [];
  protected notificationSalutations: ('frau' | 'herr' | 'divers')[] = [];
  protected notificationMinAge: number | null = null;
  protected notificationMaxAge: number | null = null;
  protected notificationIncludeMissingBirthDate = false;
  protected title = ''; protected subtitle = ''; protected publishedAt = this.toInputValue(new Date().toISOString()); protected showFrom = ''; protected showUntil = ''; protected isVisible = false;
  constructor() { void this.news.categories().then((categories) => this.categories.set(categories)).catch(() => this.error.set('Kategorien konnten nicht geladen werden.')); const id = Number(this.route.snapshot.paramMap.get('id')); if (Number.isInteger(id) && id > 0) { this.postId = id; void this.load(id); } }
  protected get isEdit(): boolean { return this.postId !== null; }
  protected selectCoverMedia(image: PickedMedia): void { this.selectedMediaPath = new URL(image.url).pathname; this.image = null; this.removeExistingImage.set(false); this.imagePreviewUrl.set(image.url); }
  protected selectImage(event: Event): void { this.setImage((event.target as HTMLInputElement).files?.[0] ?? null); } protected dragOver(event: DragEvent): void { event.preventDefault(); } protected dropImage(event: DragEvent): void { event.preventDefault(); this.setImage(event.dataTransfer?.files.item(0) ?? null); } protected removeImage(): void { this.image = null; this.selectedMediaPath = null; this.imagePreviewUrl.set(null); this.removeExistingImage.set(true); }
  protected async save(publish = false): Promise<void> {
    if (this.isLoading() || this.isSaving()) return;
    if (!this.validateContent()) return;
    const bodyHtml = this.bodyHtml.trim();

    const data = new FormData();
    data.set('title', this.title.trim());
    data.set('subtitle', this.subtitle.trim());
    data.set('bodyHtml', bodyHtml);
    data.set('publishedAt', this.publishedAt);
    data.set('showFrom', this.showFrom);
    data.set('showUntil', this.showUntil);
    data.set('isVisible', String(publish || this.isVisible));
    data.set('categoryIds', JSON.stringify(this.categoryIds));
    data.set('notificationSalutations', JSON.stringify(this.notificationSalutations));
    data.set('notificationMinAge', this.notificationMinAge === null ? '' : String(this.notificationMinAge));
    data.set('notificationMaxAge', this.notificationMaxAge === null ? '' : String(this.notificationMaxAge));
    data.set('notificationIncludeMissingBirthDate', String(this.hasAgeRange() && this.notificationIncludeMissingBirthDate));
    data.set('removeImage', String(this.removeExistingImage()));
    if (this.selectedMediaPath) data.set('mediaPath', this.selectedMediaPath);
    if (this.image) data.set('image', this.image);
    this.isSaving.set(true);
    this.error.set('');
    try {
      if (this.postId === null) await this.news.create(data);
      else await this.news.update(this.postId, data);
      await this.router.navigateByUrl('/inhalte');
    } catch {
      this.error.set('Der Beitrag konnte nicht gespeichert werden.');
    } finally {
      this.isSaving.set(false);
    }
  }
  protected async withdraw(): Promise<void> {
    this.isVisible = false;
    await this.save();
    if (this.error()) this.isVisible = true;
  }
  protected async publish(): Promise<void> {
    if (this.isLoading() || this.isSaving()) return;
    if (!this.validateContent()) return;
    const publishedAt = new Date(this.publishedAt);
    const showFrom = this.showFrom ? new Date(this.showFrom) : null;
    const showUntil = this.showUntil ? new Date(this.showUntil) : null;
    if (Number.isNaN(publishedAt.getTime()) || (showFrom && Number.isNaN(showFrom.getTime()))
      || (showUntil && Number.isNaN(showUntil.getTime()))) {
      this.error.set('Bitte prüfen Sie die Datumsangaben vor der Veröffentlichung.');
      return;
    }
    const earliest = new Date(Math.max(publishedAt.getTime(), showFrom?.getTime() ?? 0));
    if (showUntil && showUntil < earliest) {
      this.error.set('„Anzeigen bis“ muss nach dem frühesten Veröffentlichungszeitpunkt liegen.');
      return;
    }
    const isScheduled = earliest.getTime() > Date.now();
    const dateText = new Intl.DateTimeFormat('de-AT', { dateStyle: 'medium', timeStyle: 'short' }).format(earliest);
    const visibilityText = isScheduled ? `frühestens ab ${dateText}` : 'direkt nach der Veröffentlichung';
    const pushTimeText = isScheduled ? `frühestens ab ${dateText} beim darauffolgenden Hintergrundlauf` : 'beim nächsten Hintergrundlauf nach der Veröffentlichung';
    const pushNotice = this.categoryIds.length > 0
      ? `Passende Push-Mitteilungen werden ${pushTimeText} versendet, sofern Kunden die Kategorie abonniert und Push aktiviert haben.`
      : 'Ohne zugeordnete Kategorie werden keine Push-Mitteilungen versendet.';
    const confirmed = await this.dialogs.confirm(
      `„${this.title.trim() || 'Dieser Beitrag'}“ veröffentlichen? Der Beitrag wird ${visibilityText} für Kunden sichtbar. ${pushNotice} Für Apothekennews werden derzeit keine E-Mails verschickt.`,
      { title: 'Beitrag veröffentlichen', confirmLabel: 'Jetzt veröffentlichen' },
    );
    if (confirmed) await this.save(true);
  }
  protected toggleCategory(id: number, checked: boolean): void { this.categoryIds = checked ? [...this.categoryIds, id] : this.categoryIds.filter((value) => value !== id); }
  protected toggleSalutation(value: 'frau' | 'herr' | 'divers', checked: boolean): void { this.notificationSalutations = checked ? [...this.notificationSalutations, value] : this.notificationSalutations.filter((item) => item !== value); }
  protected hasAgeRange(): boolean { return this.notificationMinAge !== null || this.notificationMaxAge !== null; }
  protected onAgeRangeChanged(): void { if (!this.hasAgeRange()) this.notificationIncludeMissingBirthDate = false; }
  private isValidAge(age: number | null): boolean { return age === null || (Number.isInteger(age) && age >= 0 && age <= 120); }
  private validateContent(): boolean {
    if (!this.title.trim() || !this.subtitle.trim() || this.isEmpty(this.bodyHtml.trim())) {
      this.error.set('Bitte füllen Sie alle Pflichtfelder aus.');
      return false;
    }
    if (!this.isValidAge(this.notificationMinAge) || !this.isValidAge(this.notificationMaxAge)
      || (this.notificationMinAge !== null && this.notificationMaxAge !== null && this.notificationMinAge > this.notificationMaxAge)) {
      this.error.set('Bitte prüfen Sie die Altersgrenzen (0 bis 120 Jahre; Mindestalter darf nicht über dem Höchstalter liegen).');
      return false;
    }
    return true;
  }
  private async load(id: number): Promise<void> { this.isLoading.set(true); try { const post = await this.news.get(id); this.title = post.title; this.subtitle = post.subtitle; this.publishedAt = this.toInputValue(post.publishedAt); this.showFrom = post.showFrom ? this.toInputValue(post.showFrom) : ''; this.showUntil = post.showUntil ? this.toInputValue(post.showUntil) : ''; this.isVisible = post.isVisible; this.imagePreviewUrl.set(post.imageUrl); this.bodyHtml = post.bodyHtml; this.categoryIds = [...post.categoryIds]; this.notificationSalutations = [...post.notificationSalutations]; this.notificationMinAge = post.notificationMinAge; this.notificationMaxAge = post.notificationMaxAge; this.notificationIncludeMissingBirthDate = post.notificationIncludeMissingBirthDate; } catch { this.error.set('Der Beitrag konnte nicht geladen werden.'); } finally { this.isLoading.set(false); } }
  private setImage(file: File | null): void { if (!file) return; if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'); return; } this.image = file; this.removeExistingImage.set(false); this.imagePreviewUrl.set(URL.createObjectURL(file)); }
  private isEmpty(html: string): boolean { return html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim() === ''; } private toInputValue(value: string): string { const date = new Date(value); return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
}
