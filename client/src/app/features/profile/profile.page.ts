import { Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { CustomerProfile, ProfileService } from '../../core/profile/profile.service';

interface ProfileForm {
  displayName: string;
  phone: string;
  streetAddress: string;
  postalCode: string;
  city: string;
}

@Component({
  selector: 'app-profile-page',
  imports: [FormsModule, NgIcon],
  templateUrl: './profile.page.html',
})
export class ProfilePage {
  private readonly profiles = inject(ProfileService);
  private readonly messages = inject(StatusMessageService);
  private readonly cropCanvas = viewChild<ElementRef<HTMLCanvasElement>>('cropCanvas');
  protected readonly profile = this.profiles.profile;
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly cropOpen = signal(false);
  protected readonly zoom = signal(1);
  protected readonly cropImage = signal<HTMLImageElement | null>(null);
  protected readonly form: ProfileForm = { displayName: '', phone: '', streetAddress: '', postalCode: '', city: '' };
  private dragStart: { x: number; y: number; offsetX: number; offsetY: number } | null = null;
  private cropOffsetX = 0;
  private cropOffsetY = 0;

  async ngOnInit(): Promise<void> {
    try { this.applyProfile(await this.profiles.load()); } catch { this.messages.error('Das Profil konnte nicht geladen werden.'); } finally { this.isLoading.set(false); }
  }

  protected async save(): Promise<void> {
    this.isSaving.set(true);
    try { this.applyProfile(await this.profiles.save(this.form)); this.messages.show('Ihre Kontaktdaten wurden gespeichert.', { kind: 'success' }); } catch { this.messages.error('Das Profil konnte nicht gespeichert werden.'); } finally { this.isSaving.set(false); }
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
  private applyProfile(profile: CustomerProfile): void { this.form.displayName = profile.displayName; this.form.phone = profile.phone ?? ''; this.form.streetAddress = profile.streetAddress ?? ''; this.form.postalCode = profile.postalCode ?? ''; this.form.city = profile.city ?? ''; }
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
