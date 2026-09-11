import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { API_BASE_URL } from '../api/api.config';

@Injectable({providedIn:'root'})
export class ChatPushService {
 private readonly http=inject(HttpClient);private readonly auth=inject(AuthService);private readonly api=inject(API_BASE_URL)+'/chat/push';
 readonly supported=typeof window!=='undefined'&&window.isSecureContext&&'serviceWorker' in navigator&&'PushManager' in window&&'Notification' in window;
 readonly enabled=signal(false);readonly busy=signal(false);readonly message=signal('');
 private options(){return {headers:new HttpHeaders({Authorization:'Bearer '+this.auth.accessToken()})};}
 async enable():Promise<void>{
  if(!this.supported||this.busy())return;
  this.busy.set(true);this.message.set('');
  try{
   // Permission request runs directly from the user's click.
   const permission=await Notification.requestPermission();
   if(permission!=='granted'){this.message.set($localize`:@@chatPushDenied:Benachrichtigungen sind nicht erlaubt. Sie können die Freigabe in den Browsereinstellungen ändern.`);return;}
   const config=await firstValueFrom(this.http.get<{publicKey:string|null}>(this.api,this.options()));
   if(!config.publicKey){this.message.set($localize`:@@chatPushUnavailable:Push-Benachrichtigungen sind auf dem Server noch nicht eingerichtet.`);return;}
   const registration=await navigator.serviceWorker.register('/chat-push-sw.js',{scope:'/'});
   await navigator.serviceWorker.ready;
   const base64=config.publicKey.replace(/-/g,'+').replace(/_/g,'/');
   const key=Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length/4)*4,'=')),c=>c.charCodeAt(0));
   const subscription=await registration.pushManager.getSubscription()??await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
   await firstValueFrom(this.http.post(this.api,subscription.toJSON(),this.options()));
   this.enabled.set(true);this.message.set($localize`:@@chatPushEnabled:Benachrichtigungen wurden aktiviert.`);
  }catch{this.message.set($localize`:@@chatPushFailed:Benachrichtigungen konnten nicht aktiviert werden. Bitte versuchen Sie es erneut.`);}finally{this.busy.set(false);}
 }
 async check():Promise<void>{
  this.enabled.set(false);if(!this.supported)return;
  try{const registration=await navigator.serviceWorker.getRegistration('/');const subscription=await registration?.pushManager.getSubscription();
   if(subscription){const result=await firstValueFrom(this.http.post<{subscribed:boolean}>(this.api+'/status',{endpoint:subscription.endpoint},this.options()));this.enabled.set(result.subscribed);}
  }catch{this.enabled.set(false);}
 }
 async disable():Promise<void>{
  if(!this.supported)return;this.busy.set(true);
  try{const registration=await navigator.serviceWorker.getRegistration('/');const sub=await registration?.pushManager.getSubscription();
   if(sub){await sub.unsubscribe();await firstValueFrom(this.http.delete(this.api,{...this.options(),body:{endpoint:sub.endpoint}}));}
   this.enabled.set(false);this.message.set($localize`:@@chatPushDisabled:Benachrichtigungen wurden deaktiviert.`);
  }catch{this.enabled.set(false);this.message.set($localize`:@@chatPushDisableFailed:Bitte prüfen Sie die Benachrichtigungsfreigabe in den Browsereinstellungen.`);}finally{this.busy.set(false);}
 }
}
