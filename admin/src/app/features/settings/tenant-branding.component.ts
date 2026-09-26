import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RichTextEditorComponent } from '../../shared/rich-text-editor.component';
import { PasswordVisibilityToggleComponent } from '../../shared/password-visibility-toggle.component';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';
import { TenantBrandingService, type ContactDay, type OpeningHours, type TenantBranding, type TenantContact } from '../../core/settings/tenant-branding.service';
import { AdminChangeHistoryComponent } from '../../shared/admin-change-history.component';
import { AdminAuthService } from '../../core/auth/admin-auth.service';

type BrandingAsset = 'logo' | 'squareLogo' | 'favicon' | 'birthdayGreetingImage';
type ImageFiles = Partial<Record<BrandingAsset, File>>;
type ImageUrls = Record<BrandingAsset, string | null>;

@Component({ selector: 'app-tenant-branding', imports: [FormsModule, RichTextEditorComponent, PasswordVisibilityToggleComponent, AdminChangeHistoryComponent], templateUrl: './tenant-branding.component.html', styleUrl: './tenant-branding.component.css' })
export class TenantBrandingComponent implements OnDestroy {
  protected readonly auth = inject(AdminAuthService);
  private readonly brandingService = inject(TenantBrandingService);
  private readonly dialogs = inject(ConfirmDialogService);
  private files: ImageFiles = {};
  private readonly removedAssets = new Set<BrandingAsset>();
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly auditRefresh = signal(0);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected readonly previews = signal<ImageUrls>({ logo: null, squareLogo: null, favicon: null, birthdayGreetingImage: null });
  protected readonly isPointsPerEuroEditingEnabled = signal(false);
  protected initialPoints = 0; protected profileCompletionBonusPoints = 0; protected birthdayBonusPoints = 200; protected pointsPerEuro = 10;
  protected appointmentBookingFutureDays = 28; protected appointmentCancellationHours = 24;
  protected appointmentStaffConfirmationEnabled = false; protected showAppointmentStaffNames = false;
  protected allowDuplicateReceiptImports = false;
  protected showCustomerDebugOutput = false;
  protected familyPointSharingEnabled = false;
  protected familyPointSharingLocked = false;
  protected receiptQrPrefix = '';
  protected websiteUrl = '';
  protected appNoticeEnabled = false; protected appNoticeTitle = ''; protected appNoticeHtml = '';
  protected birthdayGreetingEnabled = false; protected birthdayGreetingTitle = ''; protected birthdayGreetingText = '';
  protected smtpHost = ''; protected smtpPort = 587; protected smtpEncryption: 'tls' | 'ssl' | 'none' = 'tls'; protected smtpUsername = ''; protected smtpPassword = ''; protected smtpFrom = ''; protected smtpPasswordConfigured = false;
  protected removeSmtpPassword = false;
  protected readonly smtpSettingsConfigured = signal(false);
  protected contactAddress = ''; protected contactPhone = ''; protected contactEmail = ''; protected contactLatitude: number | null = null; protected contactLongitude: number | null = null; protected contactMapZoom = 15; protected contactGoogleMapsUrl = ''; protected contactAdditionalHtml = '';
  protected readonly contactDays: ReadonlyArray<{ readonly key: ContactDay; readonly label: string }> = [{ key: 'monday', label: 'Montag' }, { key: 'tuesday', label: 'Dienstag' }, { key: 'wednesday', label: 'Mittwoch' }, { key: 'thursday', label: 'Donnerstag' }, { key: 'friday', label: 'Freitag' }, { key: 'saturday', label: 'Samstag' }, { key: 'sunday', label: 'Sonntag' }];
  protected openingHours: OpeningHours = this.emptyOpeningHours();
  private successTimeout: ReturnType<typeof setTimeout> | undefined;

  constructor() { void this.load(); }
  protected selectImage(asset: BrandingAsset, event: Event): void { this.setImage(asset, (event.target as HTMLInputElement).files?.[0] ?? null); }
  protected dragOver(event: DragEvent): void { event.preventDefault(); }
  protected dropImage(asset: BrandingAsset, event: DragEvent): void { event.preventDefault(); this.setImage(asset, event.dataTransfer?.files.item(0) ?? null); }
  protected setPointsPerEuroEditing(event: Event): void { this.isPointsPerEuroEditingEnabled.set((event.target as HTMLInputElement).checked); }
  protected async save(): Promise<void> {
    if (this.isSaving()) return;
    const valuesToValidate = this.isPointsPerEuroEditingEnabled() ? [this.initialPoints, this.profileCompletionBonusPoints, this.birthdayBonusPoints, this.pointsPerEuro] : [this.initialPoints, this.profileCompletionBonusPoints, this.birthdayBonusPoints];
    if (!valuesToValidate.every((value) => Number.isInteger(value) && value >= 0 && value <= 100000)) { this.error.set('Bitte prüfen Sie die Punkte-Einstellungen (0 bis 100.000).'); return; }
    const data = new FormData();
    for (const asset of ['logo', 'squareLogo', 'favicon', 'birthdayGreetingImage'] as const) {
      const file = this.files[asset];
      if (file) data.set(asset, file);
      else if (this.removedAssets.has(asset)) data.set('remove' + asset[0].toUpperCase() + asset.slice(1), 'true');
    }
    data.set('initialPoints', String(this.initialPoints)); data.set('profileCompletionBonusPoints', String(this.profileCompletionBonusPoints)); data.set('birthdayBonusPoints', String(this.birthdayBonusPoints));
    data.set('appointmentBookingFutureDays', String(this.appointmentBookingFutureDays)); data.set('appointmentCancellationHours', String(this.appointmentCancellationHours));
    data.set('appointmentStaffConfirmationEnabled', String(this.appointmentStaffConfirmationEnabled)); data.set('showAppointmentStaffNames', String(this.showAppointmentStaffNames));
    if (this.isPointsPerEuroEditingEnabled()) { data.set('pointsPerEuro', String(this.pointsPerEuro)); data.set('confirmPointsPerEuroChange', 'true'); }
    data.set('allowDuplicateReceiptImports', String(this.allowDuplicateReceiptImports));
    data.set('showCustomerDebugOutput', String(this.showCustomerDebugOutput));
    data.set('familyPointSharingEnabled', String(this.familyPointSharingEnabled));
    data.set('receiptQrPrefix', this.receiptQrPrefix.trim());
    data.set('websiteUrl', this.websiteUrl.trim());
    data.set('appNoticeEnabled', String(this.appNoticeEnabled)); data.set('appNoticeTitle', this.appNoticeTitle.trim()); data.set('appNoticeHtml', this.appNoticeHtml);
    data.set('birthdayGreetingEnabled', String(this.birthdayGreetingEnabled)); data.set('birthdayGreetingTitle', this.birthdayGreetingTitle.trim()); data.set('birthdayGreetingText', this.birthdayGreetingText.trim());
    data.set('smtpHost', this.smtpHost); data.set('smtpPort', String(this.smtpPort)); data.set('smtpEncryption', this.smtpEncryption); data.set('smtpUsername', this.smtpUsername); data.set('smtpPassword', this.smtpPassword); data.set('smtpFrom', this.smtpFrom); data.set('removeSmtpPassword', String(this.removeSmtpPassword));
    this.isSaving.set(true); this.error.set('');
    try { this.apply(await this.brandingService.update(data)); await this.saveContact(); this.files = {}; this.removedAssets.clear(); this.isPointsPerEuroEditingEnabled.set(false); this.auditRefresh.update((value) => value + 1); this.showSuccess('Ihre Einstellungen wurden gespeichert.'); } catch (error: unknown) { this.error.set(this.errorMessage(error)); } finally { this.isSaving.set(false); }
  }
  protected async clearSmtp(): Promise<void> {
    if (this.isSaving() || !this.smtpSettingsConfigured()) return;
    if (!await this.dialogs.confirm('Alle gespeicherten SMTP-Einstellungen einschließlich des Passworts entfernen? Danach verwendet die Apotheke wieder den Server-Standardversand.', { title: 'SMTP-Einstellungen entfernen', confirmLabel: 'Entfernen', destructive: true })) return;

    this.isSaving.set(true);
    this.error.set('');
    try {
      this.applySmtp(await this.brandingService.clearSmtp());
      this.showSuccess('Die SMTP-Einstellungen wurden entfernt.');
    } catch (error: unknown) {
      this.error.set(this.errorMessage(error));
    } finally {
      this.isSaving.set(false);
    }
  }
  protected dismissSuccess(): void { if (this.successTimeout !== undefined) { clearTimeout(this.successTimeout); this.successTimeout = undefined; } this.success.set(''); }
  ngOnDestroy(): void { this.dismissSuccess(); for (const asset of ['logo', 'squareLogo', 'favicon', 'birthdayGreetingImage'] as const) this.revokePreview(asset); }
  private async load(): Promise<void> { try { const [branding, contact] = await Promise.all([this.brandingService.get(), this.brandingService.getContact()]); this.apply(branding); this.applyContact(contact); } catch { this.error.set('Die Einstellungen konnten nicht geladen werden.'); } finally { this.isLoading.set(false); } }
  private setImage(asset: BrandingAsset, file: File | null): void {
    if (!file) { return; }
    const allowed = asset === 'favicon' ? ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'] : ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type) || file.size > 5 * 1024 * 1024) { this.error.set('Erlaubt sind PNG, JPEG oder WebP; beim Favicon zusätzlich ICO. Maximale Dateigröße: 5 MB.'); return; }
    this.revokePreview(asset);
    this.files = { ...this.files, [asset]: file }; this.removedAssets.delete(asset); this.error.set(''); this.previews.update((previews) => ({ ...previews, [asset]: URL.createObjectURL(file) }));
  }
  protected removeImage(asset: BrandingAsset): void {
    this.revokePreview(asset);
    delete this.files[asset];
    this.removedAssets.add(asset);
    this.previews.update((previews) => ({ ...previews, [asset]: null }));
  }
  private revokePreview(asset: BrandingAsset): void { const preview = this.previews()[asset]; if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview); }
  private showSuccess(message: string): void { this.dismissSuccess(); this.success.set(message); this.successTimeout = setTimeout(() => this.dismissSuccess(), 4000); }
  private apply(branding: TenantBranding): void { for (const asset of ['logo', 'squareLogo', 'favicon', 'birthdayGreetingImage'] as const) this.revokePreview(asset); this.previews.set({ logo: branding.logoUrl, squareLogo: branding.squareLogoUrl, favicon: branding.faviconUrl, birthdayGreetingImage: branding.birthdayGreetingImageUrl }); this.initialPoints=branding.initialPoints; this.profileCompletionBonusPoints=branding.profileCompletionBonusPoints; this.birthdayBonusPoints=branding.birthdayBonusPoints; this.pointsPerEuro=branding.pointsPerEuro; this.appointmentBookingFutureDays=branding.appointmentBookingFutureDays; this.appointmentCancellationHours=branding.appointmentCancellationHours; this.appointmentStaffConfirmationEnabled=branding.appointmentStaffConfirmationEnabled; this.showAppointmentStaffNames=branding.showAppointmentStaffNames; this.allowDuplicateReceiptImports=branding.allowDuplicateReceiptImports; this.showCustomerDebugOutput=branding.showCustomerDebugOutput; this.familyPointSharingEnabled=branding.familyPointSharingEnabled; this.familyPointSharingLocked=branding.familyPointSharingLocked; this.receiptQrPrefix=branding.receiptQrPrefix ?? ''; this.websiteUrl=branding.websiteUrl ?? ''; this.appNoticeEnabled=branding.appNoticeEnabled; this.appNoticeTitle=branding.appNoticeTitle ?? ''; this.appNoticeHtml=branding.appNoticeHtml; this.birthdayGreetingEnabled=branding.birthdayGreetingEnabled; this.birthdayGreetingTitle=branding.birthdayGreetingTitle ?? ''; this.birthdayGreetingText=branding.birthdayGreetingText ?? ''; this.applySmtp(branding); }
  private applySmtp(branding: TenantBranding): void { this.smtpHost=branding.smtpHost ?? ''; this.smtpPort=branding.smtpPort ?? 587; this.smtpEncryption=branding.smtpEncryption ?? 'tls'; this.smtpUsername=branding.smtpUsername ?? ''; this.smtpFrom=branding.smtpFrom ?? ''; this.smtpPasswordConfigured=branding.smtpPasswordConfigured; this.smtpPassword=''; this.removeSmtpPassword=false; this.smtpSettingsConfigured.set(branding.smtpHost !== null || branding.smtpPort !== null || branding.smtpEncryption !== null || branding.smtpUsername !== null || branding.smtpFrom !== null || branding.smtpPasswordConfigured); }
  private async saveContact(): Promise<void> { this.applyContact(await this.brandingService.updateContact({ address: this.contactAddress || null, phone: this.contactPhone || null, email: this.contactEmail || null, openingHours: this.openingHours, latitude: this.contactLatitude, longitude: this.contactLongitude, mapZoom: this.contactMapZoom, googleMapsUrl: this.contactGoogleMapsUrl || null, additionalHtml: this.contactAdditionalHtml })); }
  private applyContact(contact: TenantContact): void { this.contactAddress = contact.address ?? ''; this.contactPhone = contact.phone ?? ''; this.contactEmail = contact.email ?? ''; this.openingHours = { ...this.emptyOpeningHours(), ...contact.openingHours }; this.contactLatitude = contact.latitude; this.contactLongitude = contact.longitude; this.contactMapZoom = contact.mapZoom; this.contactGoogleMapsUrl = contact.googleMapsUrl ?? ''; this.contactAdditionalHtml = contact.additionalHtml; }
  private emptyOpeningHours(): OpeningHours { return { monday: '', tuesday: '', wednesday: '', thursday: '', friday: '', saturday: '', sunday: '' }; }
  private errorMessage(error: unknown): string { return error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Die Einstellungen konnten nicht gespeichert werden.'; }
}
