import { Component, OnDestroy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TenantBrandingService, type TenantBranding } from '../../core/settings/tenant-branding.service';

type BrandingAsset = 'logo' | 'squareLogo' | 'favicon';
type ImageFiles = Partial<Record<BrandingAsset, File>>;
type ImageUrls = Record<BrandingAsset, string | null>;

@Component({ selector: 'app-tenant-branding', imports: [FormsModule], templateUrl: './tenant-branding.component.html', styleUrl: './tenant-branding.component.css' })
export class TenantBrandingComponent implements OnDestroy {
  private readonly brandingService = inject(TenantBrandingService);
  private files: ImageFiles = {};
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected readonly previews = signal<ImageUrls>({ logo: null, squareLogo: null, favicon: null });
  protected readonly isPointsPerEuroEditingEnabled = signal(false);
  protected initialPoints = 0; protected birthdayBonusPoints = 200; protected pointsPerEuro = 10;
  protected allowDuplicateReceiptImports = false;
  protected showCustomerDebugOutput = false;
  protected receiptQrPrefix = '';
  protected websiteUrl = '';
  protected smtpHost = ''; protected smtpPort = 587; protected smtpEncryption: 'tls' | 'ssl' | 'none' = 'tls'; protected smtpUsername = ''; protected smtpPassword = ''; protected smtpFrom = ''; protected smtpPasswordConfigured = false;
  private successTimeout: ReturnType<typeof setTimeout> | undefined;

  constructor() { void this.load(); }
  protected selectImage(asset: BrandingAsset, event: Event): void { this.setImage(asset, (event.target as HTMLInputElement).files?.[0] ?? null); }
  protected dragOver(event: DragEvent): void { event.preventDefault(); }
  protected dropImage(asset: BrandingAsset, event: DragEvent): void { event.preventDefault(); this.setImage(asset, event.dataTransfer?.files.item(0) ?? null); }
  protected setPointsPerEuroEditing(event: Event): void { this.isPointsPerEuroEditingEnabled.set((event.target as HTMLInputElement).checked); }
  protected async save(): Promise<void> {
    const valuesToValidate = this.isPointsPerEuroEditingEnabled() ? [this.initialPoints, this.birthdayBonusPoints, this.pointsPerEuro] : [this.initialPoints, this.birthdayBonusPoints];
    if (!valuesToValidate.every(Number.isInteger)) { this.error.set('Bitte prüfen Sie die Punkte-Einstellungen.'); return; }
    const data = new FormData();
    for (const asset of ['logo', 'squareLogo', 'favicon'] as const) { const file = this.files[asset]; if (file) { data.set(asset, file); } }
    data.set('initialPoints', String(this.initialPoints)); data.set('birthdayBonusPoints', String(this.birthdayBonusPoints));
    if (this.isPointsPerEuroEditingEnabled()) { data.set('pointsPerEuro', String(this.pointsPerEuro)); data.set('confirmPointsPerEuroChange', 'true'); }
    data.set('allowDuplicateReceiptImports', String(this.allowDuplicateReceiptImports));
    data.set('showCustomerDebugOutput', String(this.showCustomerDebugOutput));
    data.set('receiptQrPrefix', this.receiptQrPrefix.trim());
    data.set('websiteUrl', this.websiteUrl.trim());
    data.set('smtpHost', this.smtpHost); data.set('smtpPort', String(this.smtpPort)); data.set('smtpEncryption', this.smtpEncryption); data.set('smtpUsername', this.smtpUsername); data.set('smtpPassword', this.smtpPassword); data.set('smtpFrom', this.smtpFrom);
    this.isSaving.set(true); this.error.set('');
    try { this.apply(await this.brandingService.update(data)); this.files = {}; this.isPointsPerEuroEditingEnabled.set(false); this.showSuccess('Ihre Einstellungen wurden gespeichert.'); } catch { this.error.set('Die Marken-Assets konnten nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
  }
  protected dismissSuccess(): void { if (this.successTimeout !== undefined) { clearTimeout(this.successTimeout); this.successTimeout = undefined; } this.success.set(''); }
  ngOnDestroy(): void { this.dismissSuccess(); }
  private async load(): Promise<void> { try { this.apply(await this.brandingService.get()); } catch { this.error.set('Die Marken-Assets konnten nicht geladen werden.'); } finally { this.isLoading.set(false); } }
  private setImage(asset: BrandingAsset, file: File | null): void {
    if (!file) { return; }
    const allowed = asset === 'favicon' ? ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'] : ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP; beim Favicon zusätzlich ICO. Maximale Dateigröße: 5 MB.'); return; }
    this.files = { ...this.files, [asset]: file }; this.error.set(''); this.previews.update((previews) => ({ ...previews, [asset]: URL.createObjectURL(file) }));
  }
  private showSuccess(message: string): void { this.dismissSuccess(); this.success.set(message); this.successTimeout = setTimeout(() => this.dismissSuccess(), 4000); }
  private apply(branding: TenantBranding): void { this.previews.set({ logo: branding.logoUrl, squareLogo: branding.squareLogoUrl, favicon: branding.faviconUrl }); this.initialPoints=branding.initialPoints; this.birthdayBonusPoints=branding.birthdayBonusPoints; this.pointsPerEuro=branding.pointsPerEuro; this.allowDuplicateReceiptImports=branding.allowDuplicateReceiptImports; this.showCustomerDebugOutput=branding.showCustomerDebugOutput; this.receiptQrPrefix=branding.receiptQrPrefix ?? ''; this.websiteUrl=branding.websiteUrl ?? ''; this.smtpHost=branding.smtpHost ?? ''; this.smtpPort=branding.smtpPort ?? 587; this.smtpEncryption=branding.smtpEncryption ?? 'tls'; this.smtpUsername=branding.smtpUsername ?? ''; this.smtpFrom=branding.smtpFrom ?? ''; this.smtpPasswordConfigured=branding.smtpPasswordConfigured; this.smtpPassword=''; }
}
