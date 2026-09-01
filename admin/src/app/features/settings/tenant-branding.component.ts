import { Component, inject, signal } from '@angular/core';
import { TenantBrandingService, type TenantBranding } from '../../core/settings/tenant-branding.service';

type BrandingAsset = 'logo' | 'squareLogo' | 'favicon';
type ImageFiles = Partial<Record<BrandingAsset, File>>;
type ImageUrls = Record<BrandingAsset, string | null>;

@Component({ selector: 'app-tenant-branding', templateUrl: './tenant-branding.component.html', styleUrl: './tenant-branding.component.css' })
export class TenantBrandingComponent {
  private readonly brandingService = inject(TenantBrandingService);
  private files: ImageFiles = {};
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly previews = signal<ImageUrls>({ logo: null, squareLogo: null, favicon: null });

  constructor() { void this.load(); }
  protected selectImage(asset: BrandingAsset, event: Event): void { this.setImage(asset, (event.target as HTMLInputElement).files?.[0] ?? null); }
  protected dragOver(event: DragEvent): void { event.preventDefault(); }
  protected dropImage(asset: BrandingAsset, event: DragEvent): void { event.preventDefault(); this.setImage(asset, event.dataTransfer?.files.item(0) ?? null); }
  protected async save(): Promise<void> {
    if (Object.keys(this.files).length === 0) { this.error.set('Bitte wählen Sie mindestens eine Datei aus.'); return; }
    const data = new FormData();
    for (const asset of ['logo', 'squareLogo', 'favicon'] as const) { const file = this.files[asset]; if (file) { data.set(asset, file); } }
    this.isSaving.set(true); this.error.set('');
    try { this.apply(await this.brandingService.update(data)); this.files = {}; } catch { this.error.set('Die Marken-Assets konnten nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
  }
  private async load(): Promise<void> { try { this.apply(await this.brandingService.get()); } catch { this.error.set('Die Marken-Assets konnten nicht geladen werden.'); } finally { this.isLoading.set(false); } }
  private setImage(asset: BrandingAsset, file: File | null): void {
    if (!file) { return; }
    const allowed = asset === 'favicon' ? ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'] : ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP; beim Favicon zusätzlich ICO. Maximale Dateigröße: 5 MB.'); return; }
    this.files = { ...this.files, [asset]: file }; this.error.set(''); this.previews.update((previews) => ({ ...previews, [asset]: URL.createObjectURL(file) }));
  }
  private apply(branding: TenantBranding): void { this.previews.set({ logo: branding.logoUrl, squareLogo: branding.squareLogoUrl, favicon: branding.faviconUrl }); }
}
