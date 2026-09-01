import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { finalize } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

interface AdminReward { id: number; title: string; subtitle: string; description: string; imageUrl: string | null; requiredPoints: number; isVisible: boolean; }

@Component({ selector: 'app-admin-rewards', imports: [FormsModule], templateUrl: './admin-rewards.component.html', styleUrl: './admin-rewards.component.css' })
export class AdminRewardsComponent {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  protected readonly rewards = signal<readonly AdminReward[]>([]);
  protected readonly error = signal('');
  protected readonly isSaving = signal(false);
  protected readonly imagePreviewUrl = signal<string | null>(null);
  protected title = ''; protected subtitle = ''; protected description = ''; protected requiredPoints = 0; protected isVisible = true;
  private image: File | null = null;

  constructor() { this.load(); }

  protected selectImage(event: Event): void { this.setImage((event.target as HTMLInputElement).files?.[0] ?? null); }
  protected dragOver(event: DragEvent): void { event.preventDefault(); }
  protected dropImage(event: DragEvent): void { event.preventDefault(); this.setImage(event.dataTransfer?.files.item(0) ?? null); }
  protected removeImage(): void { this.image = null; this.imagePreviewUrl.set(null); }
  protected create(): void {
    if (!this.title.trim() || !this.subtitle.trim() || !this.description.trim() || this.requiredPoints < 0) { this.error.set('Bitte füllen Sie alle Pflichtfelder aus.'); return; }
    const data = new FormData();
    data.set('title', this.title.trim()); data.set('subtitle', this.subtitle.trim()); data.set('description', this.description.trim());
    data.set('requiredPoints', String(this.requiredPoints)); data.set('isVisible', String(this.isVisible)); if (this.image) data.set('image', this.image);
    this.isSaving.set(true); this.error.set('');
    this.http.post<{ reward: AdminReward }>(`${this.api()}/admin/rewards`, data, { headers: this.headers() }).pipe(finalize(() => this.isSaving.set(false))).subscribe({
      next: ({ reward }) => { this.rewards.update((items) => [reward, ...items]); this.title = ''; this.subtitle = ''; this.description = ''; this.requiredPoints = 0; this.removeImage(); },
      error: () => this.error.set('Der Gutschein konnte nicht gespeichert werden.'),
    });
  }
  protected toggle(reward: AdminReward): void { this.http.patch<{ reward: AdminReward }>(`${this.api()}/admin/rewards/${reward.id}/visibility`, { isVisible: !reward.isVisible }, { headers: this.headers() }).subscribe({ next: ({ reward: updated }) => this.rewards.update((items) => items.map((item) => item.id === updated.id ? updated : item)), error: () => this.error.set('Sichtbarkeit konnte nicht geändert werden.') }); }
  protected remove(reward: AdminReward): void { if (!confirm(`„${reward.title}“ wirklich löschen?`)) return; this.http.delete(`${this.api()}/admin/rewards/${reward.id}`, { headers: this.headers() }).subscribe({ next: () => this.rewards.update((items) => items.filter((item) => item.id !== reward.id)), error: () => this.error.set('Gutschein konnte nicht gelöscht werden.') }); }
  private load(): void { this.http.get<{ rewards: AdminReward[] }>(`${this.api()}/admin/rewards`, { headers: this.headers() }).subscribe({ next: ({ rewards }) => this.rewards.set(rewards), error: () => this.error.set('Gutscheine konnten nicht geladen werden.') }); }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
  private setImage(file: File | null): void { if (!file) return; if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'); return; } this.image = file; this.error.set(''); this.imagePreviewUrl.set(URL.createObjectURL(file)); }
}
