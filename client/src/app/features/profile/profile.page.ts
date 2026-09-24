import { Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ChatPushService } from '../../core/chat/chat-push.service';
import { ThemeService } from '../../core/theme/theme.service';
import { CustomerProfile, FooterNavigationItem, ProfileService } from '../../core/profile/profile.service';
import { AuthService } from '../../core/auth/auth.service';
import { ConfirmDialogService } from '../../shared/feedback/confirm-dialog.service';

interface ProfileForm {
  username: string;
  usernamePassword: string;
  displayName: string;
  phone: string;
  streetAddress: string;
  postalCode: string;
  city: string;
  birthDate: string;
  newsletterEnabled: boolean;
  chatPushEnabled: boolean;
  rewardPushEnabled: boolean;
  newsPushEnabled: boolean;
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
  imports: [FormsModule, NgIcon],
  templateUrl: './profile.page.html',
})
export class ProfilePage {
  private readonly profiles = inject(ProfileService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly messages = inject(StatusMessageService);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly push = inject(ChatPushService);
  protected readonly theme = inject(ThemeService).activeTheme;
  private readonly cropCanvas = viewChild<ElementRef<HTMLCanvasElement>>('cropCanvas');
  protected readonly profile = this.profiles.profile;
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly emailChangeBusy = signal(false);
  protected readonly emailChangeMessage = signal('');
  protected readonly emailChangeError = signal('');
  protected emailDraft = '';
  protected emailPassword = '';
  protected deletePassword = '';
  protected readonly deleteBusy = signal(false);
  protected readonly deleteError = signal('');
  protected readonly formError = signal<string | null>(null);
  protected readonly cropOpen = signal(false);
  protected readonly zoom = signal(1);
  protected readonly cropImage = signal<HTMLImageElement | null>(null);
  protected readonly form: ProfileForm = { username: '', usernamePassword: '', displayName: '', phone: '', streetAddress: '', postalCode: '', city: '', birthDate: '', newsletterEnabled: false, chatPushEnabled: false, rewardPushEnabled: false, newsPushEnabled: false, medicationPushEnabled: false, appointmentPushEnabled: true, familyPushEnabled: true, morningReminderTime: '08:00', noonReminderTime: '12:00', eveningReminderTime: '18:00', nightReminderTime: '22:00', footerNavigationItems: ['home', 'chat', 'rewards', 'website'] };
  protected readonly footerNavigationOptions: readonly { readonly id: FooterNavigationItem; readonly label: string }[] = [
    { id: 'home', label: 'Home' },
    { id: 'chat', label: 'Chat' },
    { id: 'rewards', label: 'Prämien' },
    { id: 'coupons', label: 'Gutscheine' },
    { id: 'news', label: 'Nachrichten' },
    { id: 'appointments', label: 'Termine buchen' },
    { id: 'my-appointments', label: 'Meine Termine' },
    { id: 'medications', label: 'Medikamentenplan' },
    { id: 'family', label: 'Familie' },
    { id: 'contact', label: 'Kontakt' },
    { id: 'website', label: 'Webseite' },
  ];
  private dragStart: { x: number; y: number; offsetX: number; offsetY: number } | null = null;
  private cropOffsetX = 0;
  private cropOffsetY = 0;

  async ngOnInit(): Promise<void> {
    void this.push.initialize().then(() => this.push.check());
    try { this.applyProfile(await this.profiles.load()); } catch { this.messages.error('Das Profil konnte nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }

  protected async save(): Promise<void> {
    if (this.isSaving()) return;
    this.formError.set(null);
    if (this.form.displayName.trim().length < 2) {
      this.formError.set('Bitte geben Sie einen Namen mit mindestens 2 Zeichen ein.');
      return;
    }
    if (this.form.username.trim() !== this.profile()?.username && !/^[a-z0-9][a-z0-9._+%@-]{2,99}$/i.test(this.form.username.trim())) {
      this.formError.set('Bitte geben Sie einen Benutzernamen mit 3 bis 100 erlaubten Zeichen ein.');
      return;
    }
    const usernameChanged = this.form.username.trim().toLowerCase() !== this.profile()?.username;
    if (usernameChanged && !this.form.usernamePassword) {
      this.formError.set('Bitte bestätigen Sie den neuen Benutzernamen mit Ihrem aktuellen Passwort.');
      return;
    }
    this.isSaving.set(true);
    try {
      this.applyProfile(await this.profiles.save(this.form));
      this.form.usernamePassword = '';
      if (usernameChanged) {
        this.auth.logout();
        void this.router.navigateByUrl('/login');
      } else {
        this.messages.show('Ihre Profil- und Benachrichtigungseinstellungen wurden gespeichert.', { kind: 'success' });
      }
    } catch (error) { this.formError.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Das Profil konnte nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
  }

  protected async requestEmailChange(): Promise<void> {
    if (this.emailChangeBusy()) return;
    this.emailChangeError.set('');
    this.emailChangeMessage.set('');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.emailDraft.trim()) || !this.emailPassword) {
      this.emailChangeError.set('Bitte geben Sie eine gültige neue E-Mail-Adresse und Ihr aktuelles Passwort ein.');
      return;
    }
    this.emailChangeBusy.set(true);
    try {
      this.emailChangeMessage.set(await this.profiles.requestEmailChange(this.emailDraft.trim(), this.emailPassword));
      this.emailPassword = '';
    } catch (error) {
      this.emailChangeError.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Die Änderung konnte nicht angefordert werden.');
    } finally {
      this.emailChangeBusy.set(false);
    }
  }

  protected async deleteAccount(): Promise<void> {
    if (this.deleteBusy()) return;
    this.deleteError.set('');
    if (!this.deletePassword) {
      this.deleteError.set('Bitte geben Sie Ihr aktuelles Passwort ein.');
      return;
    }
    const password = this.deletePassword;
    const confirmed = await this.dialogs.confirm(
      'Ihr Kundenzugang bei dieser Apotheke und die zugehörigen Termine, Chats, Medikamente, Gutscheine und Punkte werden dauerhaft gelöscht. Andere Apotheken- oder Mitarbeiterzugänge bleiben bestehen. Möchten Sie fortfahren?',
      { title: 'Kundenzugang endgültig löschen?', confirmLabel: 'Kundenzugang löschen', destructive: true },
    );
    this.deletePassword = '';
    if (!confirmed) return;

    this.deleteBusy.set(true);
    try {
      await this.profiles.deleteAccount(password);
      this.auth.logout();
      await this.router.navigateByUrl('/login');
      this.messages.show('Ihr Kundenzugang wurde gelöscht.', { kind: 'success' });
    } catch (error) {
      this.deleteError.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : 'Der Kundenzugang konnte nicht gelöscht werden. Bitte versuchen Sie es erneut.');
    } finally {
      this.deleteBusy.set(false);
    }
  }

  protected togglePush(): void { if (this.push.enabled()) void this.push.disable(); else void this.push.enable(); }
  protected hasSelectedPushCategory(): boolean { return this.form.chatPushEnabled || this.form.rewardPushEnabled || this.form.newsPushEnabled || this.form.medicationPushEnabled || this.form.appointmentPushEnabled || this.form.familyPushEnabled; }
  protected isFooterNavigationItemSelected(item: FooterNavigationItem): boolean { return this.form.footerNavigationItems.includes(item); }
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
  protected initials(): string { return this.form.displayName.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'K'; }
  private applyProfile(profile: CustomerProfile): void { this.form.username = profile.username; this.form.displayName = profile.displayName; this.form.phone = profile.phone ?? ''; this.form.streetAddress = profile.streetAddress ?? ''; this.form.postalCode = profile.postalCode ?? ''; this.form.city = profile.city ?? ''; this.form.birthDate = profile.birthDate ?? ''; this.form.newsletterEnabled = profile.newsletterEnabled; this.form.chatPushEnabled = profile.chatPushEnabled; this.form.rewardPushEnabled = profile.rewardPushEnabled; this.form.newsPushEnabled = profile.newsPushEnabled; this.form.medicationPushEnabled = profile.medicationPushEnabled; this.form.appointmentPushEnabled = profile.appointmentPushEnabled; this.form.familyPushEnabled = profile.familyPushEnabled; this.form.morningReminderTime = profile.morningReminderTime; this.form.noonReminderTime = profile.noonReminderTime; this.form.eveningReminderTime = profile.eveningReminderTime; this.form.nightReminderTime = profile.nightReminderTime; this.form.footerNavigationItems = [...profile.footerNavigationItems]; }
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
