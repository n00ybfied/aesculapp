import { HttpClient, HttpHeaders, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

interface MediaAsset { readonly id: number; readonly url: string; readonly name: string; readonly width: number; readonly height: number; }
interface Usage { readonly label: string; readonly editUrl: string; }
@Component({ selector: 'app-media-library', imports: [RouterLink], templateUrl: './media-library.component.html', styleUrl: './media-library.component.css' })
export class MediaLibraryComponent {
  private readonly http = inject(HttpClient); private readonly auth = inject(AdminAuthService);
  protected readonly media = signal<readonly MediaAsset[]>([]); protected readonly error = signal(''); protected readonly isUploading = signal(false); protected readonly usage = signal<readonly Usage[] | null>(null);
  constructor() { void this.load(); }
  protected select(event: Event): void { this.upload((event.target as HTMLInputElement).files); }
  protected drop(event: DragEvent): void { event.preventDefault(); this.upload(event.dataTransfer?.files ?? null); }
  protected async remove(asset: MediaAsset): Promise<void> { this.error.set(''); this.usage.set(null); try { await firstValueFrom(this.http.delete(this.api() + '/admin/media/' + asset.id, { headers: this.headers() })); this.media.update(items => items.filter(item => item.id !== asset.id)); } catch (error) { const response = error as HttpErrorResponse; if (response.status === 409) this.usage.set((response.error?.usage ?? []) as Usage[]); else this.error.set('Das Bild konnte nicht gelöscht werden.'); } }
  private async load(): Promise<void> { try { const response = await firstValueFrom(this.http.get<{ media: MediaAsset[] }>(this.api() + '/admin/media', { headers: this.headers() })); this.media.set(response.media); } catch { this.error.set('Medien konnten nicht geladen werden.'); } }
  private async upload(files: FileList | null): Promise<void> { if (!files?.length || this.isUploading()) return; const data = new FormData(); Array.from(files).forEach(file => data.append('images[]', file)); this.isUploading.set(true); this.error.set(''); try { const response = await firstValueFrom(this.http.post<{ media: MediaAsset[] }>(this.api() + '/admin/media', data, { headers: this.headers() })); this.media.update(items => [...response.media, ...items]); } catch { this.error.set('Upload fehlgeschlagen. Erlaubt sind PNG, JPEG oder WebP bis 5 MB.'); } finally { this.isUploading.set(false); } }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
