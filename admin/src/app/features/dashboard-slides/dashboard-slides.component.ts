import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';
import { MediaPickerComponent, type PickedMedia } from '../../shared/media-picker.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';
import { AdminChangeHistoryComponent } from '../../shared/admin-change-history.component';

interface DashboardSlide {
  readonly id: number;
  readonly imageUrl: string;
  readonly linkUrl: string | null;
  readonly position: number;
}
interface DashboardSliderSettings {
  readonly transition: 'fade' | 'slide';
  readonly animationDurationMs: number;
  readonly delayMs: number;
  readonly autoplay: boolean;
}

@Component({
  selector: 'app-dashboard-slides',
  imports: [FormsModule, MediaPickerComponent, AdminChangeHistoryComponent],
  templateUrl: './dashboard-slides.component.html',
  styleUrl: './dashboard-slides.component.css',
})
export class DashboardSlidesComponent {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly slides = signal<readonly DashboardSlide[]>([]);
  protected readonly editorOpen = signal(false);
  protected readonly editingId = signal<number | null>(null);
  protected readonly imageUrl = signal<string | null>(null);
  protected readonly error = signal('');
  protected readonly saving = signal(false);
  protected readonly settingsSaving = signal(false);
  protected readonly auditRefresh = signal(0);
  protected linkUrl = '';
  protected readonly transition: 'slide' = 'slide';
  protected animationDurationMs = 400;
  protected delayMs = 6000;
  protected autoplay = true;
  private imagePath: string | null = null;

  constructor() { void this.load(); }

  protected openCreate(): void {
    this.editingId.set(null); this.imageUrl.set(null); this.imagePath = null; this.linkUrl = ''; this.error.set(''); this.editorOpen.set(true);
  }
  protected openEdit(slide: DashboardSlide): void {
    this.editingId.set(slide.id); this.imageUrl.set(slide.imageUrl); this.imagePath = new URL(slide.imageUrl).pathname; this.linkUrl = slide.linkUrl ?? ''; this.error.set(''); this.editorOpen.set(true);
  }
  protected closeEditor(): void { this.editorOpen.set(false); }
  protected selectImage(item: PickedMedia): void { this.imageUrl.set(item.url); this.imagePath = new URL(item.url).pathname; }

  protected async save(): Promise<void> {
    if (!this.imagePath || this.saving()) { this.error.set('Bitte wählen Sie ein Bild aus der Mediathek oder laden Sie eines hoch.'); return; }
    const data = new FormData(); data.set('imagePath', this.imagePath); data.set('linkUrl', this.linkUrl.trim());
    this.saving.set(true); this.error.set('');
    try {
      const id = this.editingId();
      await firstValueFrom(this.http.post(id === null ? this.api() : `${this.api()}/${id}`, data, { headers: this.headers() }));
      this.editorOpen.set(false); await this.load();
    } catch { this.error.set('Sliderbild konnte nicht gespeichert werden. Links müssen mit https:// beginnen oder innerhalb der App mit / starten.'); }
    finally { this.saving.set(false); }
  }

  protected async remove(slide: DashboardSlide): Promise<void> {
    if (!await this.dialogs.confirm('Dieses Sliderbild wirklich entfernen?', { title: 'Sliderbild entfernen', confirmLabel: 'Entfernen', destructive: true })) return;
    this.error.set('');
    try { await firstValueFrom(this.http.delete(`${this.api()}/${slide.id}`, { headers: this.headers() })); await this.load(); }
    catch { this.error.set('Sliderbild konnte nicht entfernt werden.'); }
  }

  protected async move(slide: DashboardSlide, offset: number): Promise<void> {
    const items = [...this.slides()]; const index = items.findIndex(item => item.id === slide.id); const destination = index + offset;
    if (index < 0 || destination < 0 || destination >= items.length) return;
    [items[index], items[destination]] = [items[destination], items[index]]; this.slides.set(items);
    try { const result = await firstValueFrom(this.http.put<{ slides: DashboardSlide[] }>(`${this.api()}/order`, { ids: items.map(item => item.id) }, { headers: this.headers() })); this.slides.set(result.slides); }
    catch { this.error.set('Reihenfolge konnte nicht gespeichert werden.'); await this.load(); }
  }

  protected async saveSettings(): Promise<void> {
    if (this.settingsSaving()) return;
    const duration = Number(this.animationDurationMs);
    const delay = Number(this.delayMs);
    if (!Number.isFinite(duration) || duration < 100 || duration > 3000 || !Number.isFinite(delay) || delay < 1000 || delay > 60000) {
      this.error.set('Bitte Animationsdauer (100–3.000 ms) und Wechselabstand (1.000–60.000 ms) prüfen.');
      return;
    }
    this.settingsSaving.set(true); this.error.set('');
    try {
      const settings: DashboardSliderSettings = { transition: this.transition, animationDurationMs: Number(this.animationDurationMs), delayMs: Number(this.delayMs), autoplay: this.autoplay };
      await firstValueFrom(this.http.put(this.api() + '/settings', settings, { headers: this.headers() }));
      this.auditRefresh.update((value) => value + 1);
    } catch { this.error.set('Slider-Einstellungen konnten nicht gespeichert werden. Die Animationsdauer muss zwischen 100 und 3.000 ms, der Wechselabstand zwischen 1.000 und 60.000 ms liegen.'); }
    finally { this.settingsSaving.set(false); }
  }

  private async load(): Promise<void> {
    try {
      const result = await firstValueFrom(this.http.get<{ slides: DashboardSlide[]; settings: DashboardSliderSettings }>(this.api(), { headers: this.headers() }));
      this.slides.set(result.slides);
      this.animationDurationMs = result.settings.animationDurationMs;
      this.delayMs = result.settings.delayMs;
      this.autoplay = result.settings.autoplay;
    }
    catch { this.error.set('Sliderbilder konnten nicht geladen werden.'); }
  }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }); }
  private api(): string { return this.baseUrl() + '/admin/dashboard/slides'; }
  private baseUrl(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
