import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminRewardService } from '../../core/rewards/admin-reward.service';
import { RichTextEditorComponent } from '../../shared/rich-text-editor.component';
import { MediaPickerComponent, PickedMedia } from '../../shared/media-picker.component';
@Component({
 selector:'app-reward-editor', imports:[ReactiveFormsModule,RouterLink,RichTextEditorComponent,MediaPickerComponent],
 template:`
 <a routerLink="/praemien">← Zu allen Gutscheinen</a>
 <h2>{{id===null?'Gutschein anlegen':'Gutschein bearbeiten'}}</h2>
 @if(loading()){<p role="status">Gutschein wird geladen …</p>}
 @else if(loadFailed()){<p role="alert">{{error()}}</p>}
 @else {
 <form [formGroup]="form" (ngSubmit)="save()">
 <fieldset [disabled]="saving()">
 <div class="fields">
 <label>Titel<input formControlName="title" maxlength="160" /></label>
 <label>Untertitel<input formControlName="subtitle" maxlength="200" /></label>
 <label>Punktekosten<input formControlName="requiredPoints" type="number" min="0" step="1" /></label>
 <label class="check"><input type="checkbox" formControlName="isVisible" /> Sichtbar in der Kunden-App</label>
 </div>
 <p>Beschreibung</p><app-rich-text-editor [(value)]="description" />
 <p>Gutscheinbild (optional)</p><app-media-picker (selected)="selectImage($event)" />
 @if(imageUrl()){<div class="preview"><img [src]="imageUrl()" alt="Gutscheinbild" /><button type="button" (click)="removeImage()">Bild entfernen</button></div>}
 @if(error()){<p role="alert">{{error()}}</p>}
 <div class="actions"><button type="submit">{{saving()?'Speichert …':'Gutschein speichern'}}</button><a routerLink="/praemien">Abbrechen</a></div>
 </fieldset>
 </form>}
 `,
 styles:[`
 :host{display:block;max-width:60rem}form{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:1rem;padding:1.5rem}
 fieldset{border:0;padding:0;min-width:0}.fields{display:grid;grid-template-columns:1fr 1fr;gap:1rem}label{display:grid;gap:.4rem;font-weight:400}input{min-width:0;min-height:2.8rem;padding:.6rem;border:1px solid var(--admin-border);border-radius:.5rem;font:inherit}
 .check{display:flex;align-items:center;gap:.6rem}.check input{width:1.4rem}
 .preview{display:flex;gap:1rem;align-items:center;margin:1rem 0}.preview img{width:8rem;height:6rem;object-fit:contain}
 button{min-height:2.8rem;padding:.6rem 1rem;border:1px solid var(--admin-border);border-radius:.5rem;background:var(--admin-primary);color:white}
 .actions{display:flex;gap:1rem;align-items:center;margin-top:1.5rem}a{color:var(--admin-primary-strong)}[role=alert]{color:var(--admin-danger)}
 @media(max-width:650px){.fields{grid-template-columns:1fr}form{padding:1rem}}
 `]
})
export class RewardEditorComponent {
 private readonly service=inject(AdminRewardService);
 private readonly router=inject(Router);
 private readonly route=inject(ActivatedRoute);
 protected readonly id=this.route.snapshot.paramMap.has('id')?Number(this.route.snapshot.paramMap.get('id')):null;
 protected readonly form=inject(FormBuilder).nonNullable.group({title:['',[Validators.required,Validators.maxLength(160)]],subtitle:['',[Validators.required,Validators.maxLength(200)]],requiredPoints:[0,[Validators.required,Validators.min(0)]],isVisible:[true]});
 protected readonly description=signal('');
 protected readonly imageUrl=signal<string|null>(null);
 protected readonly loading=signal(this.id!==null);
 protected readonly loadFailed=signal(false);
 protected readonly saving=signal(false);
 protected readonly error=signal('');
 private mediaPath:string|null=null;
 private imageRemoved=false;
 constructor(){if(this.id!==null)void this.load();}
 private async load(){try{const item=await this.service.get(this.id!);this.form.patchValue(item);this.description.set(item.description);this.imageUrl.set(item.imageUrl);}catch{this.loadFailed.set(true);this.error.set('Gutschein konnte nicht geladen werden.');}finally{this.loading.set(false);}}
 protected selectImage(item:PickedMedia){this.mediaPath=new URL(item.url).pathname;this.imageUrl.set(item.url);this.imageRemoved=false;}
 protected removeImage(){this.mediaPath=null;this.imageUrl.set(null);this.imageRemoved=true;}
 protected async save(){
 if(this.saving())return;
 const value=this.form.getRawValue();
 if(this.form.invalid||!value.title.trim()||!value.subtitle.trim()||!Number.isInteger(value.requiredPoints)||!this.description().replace(/<[^>]*>/g,'').trim()&&!this.description().includes('<img')){this.error.set('Bitte Titel, Untertitel, Beschreibung und ganzzahlige Punktekosten prüfen.');this.form.markAllAsTouched();return;}
 const data=new FormData();Object.entries(value).forEach(([key,value])=>data.set(key,String(value)));data.set('description',this.description());data.set('removeImage',String(this.imageRemoved));if(this.mediaPath)data.set('mediaPath',this.mediaPath);
 this.saving.set(true);this.error.set('');try{await this.service.save(this.id,data);await this.router.navigateByUrl('/praemien');}catch{this.error.set('Gutschein konnte nicht gespeichert werden. Bitte Eingaben prüfen.');}finally{this.saving.set(false);}
 }
}
