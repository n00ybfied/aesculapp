import { Component,inject,signal } from '@angular/core';
import { ActivatedRoute,Router,RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AdminCouponService } from '../../core/coupons/admin-coupon.service';
import { RichTextEditorComponent } from '../../shared/rich-text-editor.component';
import { MediaPickerComponent,PickedMedia } from '../../shared/media-picker.component';

@Component({standalone:true,imports:[FormsModule,RouterLink,RichTextEditorComponent,MediaPickerComponent],template:`
<a routerLink="/gutscheine">← Zu allen Gutscheinen</a><h2>{{id===null?'Gutschein anlegen':'Gutschein bearbeiten'}}</h2>
<form #couponForm="ngForm" (ngSubmit)="save(couponForm.form.valid)" novalidate>
<label><span>Titel <b>*</b></span><input [(ngModel)]="title" name="title" required maxlength="160" #titleInput="ngModel" [attr.aria-invalid]="titleInput.invalid&&submitted()"/></label>
@if(titleInput.invalid&&submitted()){<p class="field-error">Bitte einen Titel eingeben.</p>}
<label><span>Untertitel <b>*</b></span><input [(ngModel)]="subtitle" name="subtitle" required maxlength="200" #subtitleInput="ngModel" [attr.aria-invalid]="subtitleInput.invalid&&submitted()"/></label>
@if(subtitleInput.invalid&&submitted()){<p class="field-error">Bitte einen Untertitel eingeben.</p>}
<label>Verfügbar ab (optional)<input type="datetime-local" [(ngModel)]="availableFrom" name="availableFrom"/></label><label>Verfügbar bis (optional)<input type="datetime-local" [(ngModel)]="availableUntil" name="availableUntil"/></label>
<label class="check"><input type="checkbox" [(ngModel)]="isVisible" name="visible"/> Sichtbar in der Kunden-App</label>
<div class="wide"><p>Beschreibung <b>*</b></p><app-rich-text-editor [(value)]="description"/>@if(submitted()&&emptyDescription()){<p class="field-error">Bitte eine Beschreibung eingeben.</p>}</div>
<div class="wide"><p>Gutscheinbild (optional)</p><app-media-picker (selected)="selectImage($event)"/>@if(imageUrl()){<div class="preview"><img [src]="imageUrl()" alt="Gewähltes Gutscheinbild"/><button type="button" (click)="removeImage()">Bild entfernen</button></div>}</div>
@if(error()){<p class="error" role="alert">{{error()}}</p>}<button type="submit">Gutschein speichern</button></form>`,styles:[`:host{display:block;max-width:60rem}form{display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:1.5rem;border:1px solid var(--admin-border);border-radius:1rem;background:var(--admin-surface)}label{display:grid;gap:.4rem;font-weight:400}label>span{display:inline}b{color:var(--admin-danger)}input{min-height:2.8rem;padding:.6rem;border:1px solid var(--admin-border);border-radius:.5rem;font:inherit}input[aria-invalid=true]{border-color:var(--admin-danger)}.check{display:flex;align-items:center}.check input{width:1.4rem}.wide,button,.error{grid-column:1/-1}.field-error,.error{margin:0;color:var(--admin-danger)}.preview{display:flex;gap:1rem;align-items:center;margin-top:1rem}.preview img{width:8rem;height:6rem;object-fit:contain}.preview button{background:transparent;color:var(--admin-danger);border:1px solid var(--admin-danger)}button{justify-self:start;min-height:2.8rem;padding:.6rem 1rem;border:0;border-radius:.5rem;background:var(--admin-primary);color:#fff;font-weight:700;cursor:pointer}@media(max-width:650px){form{grid-template-columns:1fr}}`]})
export class CouponEditorComponent {
  private readonly service = inject(AdminCouponService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  protected readonly id = this.route.snapshot.paramMap.has('id') ? Number(this.route.snapshot.paramMap.get('id')) : null;
  protected title = '';
  protected subtitle = '';
  protected description = signal('');
  protected availableFrom = '';
  protected availableUntil = '';
  protected isVisible = true;
  protected readonly imageUrl = signal<string | null>(null);
  protected readonly submitted = signal(false);
  protected readonly error = signal('');
  private mediaPath: string | null = null;
  private imageRemoved = false;

  constructor() { if (this.id !== null) void this.load(); }

  private async load(): Promise<void> {
    const coupon = await this.service.get(this.id!);
    this.title = coupon.title;
    this.subtitle = coupon.subtitle;
    this.description.set(coupon.description);
    this.availableFrom = coupon.availableFrom?.slice(0, 16) ?? '';
    this.availableUntil = coupon.availableUntil?.slice(0, 16) ?? '';
    this.isVisible = coupon.isVisible;
    this.imageUrl.set(coupon.imageUrl);
  }

  protected emptyDescription(): boolean { return !this.description().replace(/<[^>]*>/g, '').trim() && !this.description().includes('<img'); }
  protected selectImage(item: PickedMedia): void { this.mediaPath = new URL(item.url).pathname; this.imageUrl.set(item.url); this.imageRemoved = false; }
  protected removeImage(): void { this.mediaPath = null; this.imageUrl.set(null); this.imageRemoved = true; }

  protected async save(valid: boolean): Promise<void> {
    this.submitted.set(true);
    if (!valid || this.emptyDescription() || (this.availableFrom && this.availableUntil && this.availableFrom > this.availableUntil)) {
      this.error.set('Bitte die markierten Pflichtfelder und den Zeitraum prüfen.');
      return;
    }
    const data = new FormData();
    data.set('title', this.title.trim());
    data.set('subtitle', this.subtitle.trim());
    data.set('description', this.description());
    data.set('availableFrom', this.availableFrom);
    data.set('availableUntil', this.availableUntil);
    data.set('isVisible', String(this.isVisible));
    if (this.imageRemoved) data.set('removeImage', 'true');
    if (this.mediaPath) data.set('mediaPath', this.mediaPath);
    await this.service.save(this.id, data);
    await this.router.navigateByUrl('/gutscheine');
  }
}
