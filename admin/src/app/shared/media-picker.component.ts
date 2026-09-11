import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Component, inject, output, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../core/auth/admin-auth.service';

export interface PickedMedia { readonly url: string; readonly name: string; }

@Component({ selector: 'app-media-picker', templateUrl: './media-picker.component.html', styleUrl: './media-picker.component.css' })
export class MediaPickerComponent {
  private readonly http = inject(HttpClient); private readonly auth = inject(AdminAuthService);
  protected readonly open = signal(false); protected readonly media = signal<readonly (PickedMedia & { id: number })[]>([]); protected readonly uploading = signal(false);
  readonly selected = output<PickedMedia>();
  protected async show(): Promise<void> { this.open.set(true); const result = await firstValueFrom(this.http.get<{ media: (PickedMedia & { id: number })[] }>(this.api() + '/admin/media', { headers: this.headers() })); this.media.set(result.media); }
  protected choose(item: PickedMedia): void { this.selected.emit(item); this.open.set(false); }
  protected upload(event: Event): void { void this.send((event.target as HTMLInputElement).files); }
  protected drop(event: DragEvent): void { event.preventDefault(); void this.send(event.dataTransfer?.files ?? null); }
  private async send(files: FileList | null): Promise<void> { if (!files?.length) return; const data = new FormData(); Array.from(files).forEach(file => data.append('images[]', file)); this.uploading.set(true); try { const result = await firstValueFrom(this.http.post<{ media: (PickedMedia & { id: number })[] }>(this.api() + '/admin/media', data, { headers: this.headers() })); this.media.update(items => [...result.media, ...items]); } finally { this.uploading.set(false); } }
  private headers(): HttpHeaders { return new HttpHeaders({ Authorization: 'Bearer ' + this.auth.accessToken() }); }
  private api(): string { return location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? 'http://localhost:6080/api/v1' : 'https://api.aesculapp.floatbox.at/api/v1'; }
}
