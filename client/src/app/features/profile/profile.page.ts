import { Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ChatPushService } from '../../core/chat/chat-push.service';
import { ThemeService } from '../../core/theme/theme.service';
import { CustomerProfile, FooterNavigationItem, ProfileService } from '../../core/profile/profile.service';
import { AchievementCelebrationService } from '../../core/achievements/achievement-celebration.service';

interface ProfileForm {
  displayName: string;
  firstName: string;
  lastName: string;
  phone: string;
  streetAddress: string;
  postalCode: string;
  city: string;
  birthDate: string;
  salutation: 'frau' | 'herr' | 'divers' | null;
  newsletterEnabled: boolean;
  chatPushEnabled: boolean;
  rewardPushEnabled: boolean;
  newsPushEnabled: boolean;
  newsCategoryIds: number[];
  medicationPushEnabled: boolean;
  appointmentPushEnabled: boolean;
  familyPushEnabled: boolean;
  morningReminderTime: string;
  noonReminderTime: string;
  eveningReminderTime: string;
  nightReminderTime: string;
  footerNavigationItems: FooterNavigationItem[];
}

@Component({
  selector: 'app-profile-page',
  imports: [FormsModule, NgIcon, RouterLink],
  templateUrl: './profile.page.html',
})
export class ProfilePage {
  private readonly profiles = inject(ProfileService);
  private readonly messages = inject(StatusMessageService);
  private readonly celebrations = inject(AchievementCelebrationService);
  protected readonly push = inject(ChatPushService);
  protected readonly theme = inject(ThemeService).activeTheme;
  private readonly cropCanvas = viewChild<ElementRef<HTMLCanvasElement>>('cropCanvas');
  private readonly contactDetails = viewChild<ElementRef<HTMLDetailsElement>>('contactDetails');
  protected readonly profile = this.profiles.profile;
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly newsCategories = signal<readonly { id: number; name: string }[]>([]);
  protected readonly cropOpen = signal(false);
  protected readonly zoom = signal(1);
  protected readonly cropImage = signal<HTMLImageElement | null>(null);
  protected readonly form: ProfileForm = { displayName: '', firstName: '', lastName: '', phone: '', streetAddress: '', postalCode: '', city: '', birthDate: '', salutation: null, newsletterEnabled: false, chatPushEnabled: false, rewardPushEnabled: false, newsPushEnabled: false, newsCategoryIds: [], medicationPushEnabled: false, appointmentPushEnabled: true, familyPushEnabled: true, morningReminderTime: '08:00', noonReminderTime: '12:00', eveningReminderTime: '18:00', nightReminderTime: '22:00', footerNavigationItems: ['home', 'chat', 'rewards', 'website'] };
  protected readonly footerNavigationOptions: readonly { readonly id: FooterNavigationItem; readonly label: string }[] = [
    { id: 'home', label: 'Home' },
    { id: 'chat', label: 'Chat' },
    { id: 'rewards', label: 'Prämien' },
    { id: 'coupons', label: 'Gutscheine' },
    { id: 'news', label: 'Nachrichten' },
    { id: 'appointments', label: 'Termine' },
    { id: 'medications', label: 'Medikamentenplan' },
    { id: 'family', label: 'Familie' },
    { id: 'contact', label: 'Kontakt' },
    { id: 'website', label: 'Webseite' },
    { id: 'achievements', label: 'Trophäen' },
  ];
  private dragStart: { x: number; y: number; offsetX: number; offsetY: number } | null = null;
  private cropOffsetX = 0;
  private cropOffsetY = 0;

  async ngOnInit(): Promise<void> {
    void this.push.initialize().then(() => this.push.check());
    void this.profiles.loadNewsCategories().then((categories) => this.newsCategories.set(categories)).catch(() => this.messages.error('Nachrichtenkategorien konnten nicht geladen werden.'));
    try { this.applyProfile(await this.profiles.load()); } catch { this.messages.error('Das Profil konnte nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }

  protected async save(): Promise<void> {
    if (this.isSaving()) return;
    this.formError.set(null);
    if ((this.form.firstName.trim() + ' ' + this.form.lastName.trim()).length > 160) {
      this.openContactDetails();
      this.formError.set('Vor- und Nachname sind zusammen zu lang.');
      return;
    }
    this.isSaving.set(true);
    try {
      const previousProfile = this.profile();
      const bonusAlreadyAwarded = previousProfile?.profileCompletionBonusAwarded ?? false;
      const achievementAlreadyCompleted = bonusAlreadyAwarded || (previousProfile?.profileComplete ?? false);
      const savedProfile = await this.profiles.save(this.form);
      this.applyProfile(savedProfile);
      if (!achievementAlreadyCompleted && (savedProfile.profileComplete || savedProfile.profileCompletionBonusAwarded)) {
        this.celebrations.celebrate({ title: 'Profil vervollständigen', points: !bonusAlreadyAwarded && savedProfile.profileCompletionBonusAwarded ? savedProfile.profileCompletionBonusPoints : 0 });
      } else {
        this.messages.show(!bonusAlreadyAwarded && savedProfile.profileCompletionBonusAwarded
          ? `Für Ihr vollständiges Profil haben wir Ihnen ${savedProfile.profileCompletionBonusPoints} Punkte gutgeschrieben!`
          : 'Ihre Profil- und Benachrichtigungseinstellungen wurden gespeichert.', { kind: 'success' });
      }
    } catch (error) {
      this.openContactDetails();
      this.formError.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Das Profil konnte nicht gespeichert werden.');
    } finally { this.isSaving.set(false); }
  }

  protected togglePush(): void { if (this.push.enabled()) void this.push.disable(); else void this.push.enable(); }
  protected hasSelectedPushCategory(): boolean { return this.form.chatPushEnabled || this.form.rewardPushEnabled || this.form.newsPushEnabled || this.form.medicationPushEnabled || this.form.appointmentPushEnabled || this.form.familyPushEnabled; }
  protected isFooterNavigationItemSelected(item: FooterNavigationItem): boolean { return this.form.footerNavigationItems.includes(item); }
  protected toggleNewsCategory(id: number, checked: boolean): void { this.form.newsCategoryIds = checked ? [...this.form.newsCategoryIds, id] : this.form.newsCategoryIds.filter((selected) => selected !== id); }
  protected toggleFooterNavigationItem(item: FooterNavigationItem, selected: boolean): void {
    if (selected) {
      if (this.form.footerNavigationItems.length >= 4) {
        this.messages.error('Sie können maximal vier Schnellzugriffe auswählen.');
        return;
      }
      this.form.footerNavigationItems.push(item);
      return;
    }
    this.form.footerNavigationItems = this.form.footerNavigationItems.filter((selectedItem) => selectedItem !== item);
  }

  protected selectPhoto(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (file === undefined) return;
    if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) { this.messages.error('Bitte wählen Sie ein Bild bis maximal 5 MB.'); return; }
    const image = new Image();
    image.onload = () => { this.cropImage.set(image); this.zoom.set(1); this.cropOffsetX = 0; this.cropOffsetY = 0; this.cropOpen.set(true); setTimeout(() => this.drawCropCanvas()); };
    image.src = URL.createObjectURL(file);
  }

  protected updateZoom(value: string): void { this.zoom.set(Number(value)); this.drawCropCanvas(); }
  protected startDrag(event: PointerEvent): void {
    const canvas = this.cropCanvas()?.nativeElement;
    if (canvas === undefined) return;
    canvas.setPointerCapture(event.pointerId);
    this.dragStart = { x: event.clientX, y: event.clientY, offsetX: this.cropOffsetX, offsetY: this.cropOffsetY };
  }
  protected drag(event: PointerEvent): void {
    if (this.dragStart === null) return;
    this.cropOffsetX = this.dragStart.offsetX + event.clientX - this.dragStart.x;
    this.cropOffsetY = this.dragStart.offsetY + event.clientY - this.dragStart.y;
    this.drawCropCanvas();
  }
  protected stopDrag(): void { this.dragStart = null; }
  protected closeCrop(): void { this.cropOpen.set(false); this.cropImage.set(null); }
  protected async savePhoto(): Promise<void> {
    const canvas = this.cropCanvas()?.nativeElement;
    if (canvas === undefined) return;
    this.isSaving.set(true);
    try {
      const photo = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
      if (photo === null) throw new Error('Image export failed.');
      this.applyProfile(await this.profiles.uploadPhoto(photo));
      this.closeCrop();
      this.messages.show('Ihr Profilbild wurde gespeichert.', { kind: 'success' });
    } catch { this.messages.error('Das Profilbild konnte nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
  }
  protected async removePhoto(): Promise<void> {
    if (this.isSaving() || !this.profile()?.profileImageUrl) return;
    this.isSaving.set(true);
    try {
      this.applyProfile(await this.profiles.deletePhoto());
      this.messages.show('Ihr Profilbild wurde entfernt.', { kind: 'success' });
    } catch {
      this.messages.error('Das Profilbild konnte nicht entfernt werden.');
    } finally {
      this.isSaving.set(false);
    }
  }
  protected initials(): string { return this.form.displayName.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'K'; }
  private openContactDetails(): void {
    const details = this.contactDetails()?.nativeElement;
    if (details) details.open = true;
  }
  private applyProfile(profile: CustomerProfile): void { this.form.displayName = profile.displayName; this.form.firstName = profile.firstName ?? ''; this.form.lastName = profile.lastName ?? ''; this.form.phone = profile.phone ?? ''; this.form.streetAddress = profile.streetAddress ?? ''; this.form.postalCode = profile.postalCode ?? ''; this.form.city = profile.city ?? ''; this.form.birthDate = profile.birthDate ?? ''; this.form.salutation = profile.salutation; this.form.newsletterEnabled = profile.newsletterEnabled; this.form.chatPushEnabled = profile.chatPushEnabled; this.form.rewardPushEnabled = profile.rewardPushEnabled; this.form.newsPushEnabled = profile.newsPushEnabled; this.form.newsCategoryIds = [...profile.newsCategoryIds]; this.form.medicationPushEnabled = profile.medicationPushEnabled; this.form.appointmentPushEnabled = profile.appointmentPushEnabled; this.form.familyPushEnabled = profile.familyPushEnabled; this.form.morningReminderTime = profile.morningReminderTime; this.form.noonReminderTime = profile.noonReminderTime; this.form.eveningReminderTime = profile.eveningReminderTime; this.form.nightReminderTime = profile.nightReminderTime; this.form.footerNavigationItems = [...new Set(profile.footerNavigationItems.map((item) => item === 'my-appointments' ? 'appointments' : item))]; }
  private drawCropCanvas(): void {
    const canvas = this.cropCanvas()?.nativeElement;
    const image = this.cropImage();
    if (canvas === undefined || image === null) return;
    const context = canvas.getContext('2d');
    if (context === null) return;
    const size = canvas.width;
    const scale = Math.max(size / image.naturalWidth, size / image.naturalHeight) * this.zoom();
    const width = image.naturalWidth * scale;
    const height = image.naturalHeight * scale;
    const maxX = Math.max(0, (width - size) / 2);
    const maxY = Math.max(0, (height - size) / 2);
    this.cropOffsetX = Math.max(-maxX, Math.min(maxX, this.cropOffsetX));
    this.cropOffsetY = Math.max(-maxY, Math.min(maxY, this.cropOffsetY));
    context.clearRect(0, 0, size, size);
    context.save(); context.beginPath(); context.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2); context.clip();
    context.drawImage(image, (size - width) / 2 + this.cropOffsetX, (size - height) / 2 + this.cropOffsetY, width, height);
    context.restore();
  }
}
