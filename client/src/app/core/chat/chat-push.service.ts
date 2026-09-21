import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { API_BASE_URL } from '../api/api.config';

@Injectable({providedIn:'root'})
export class ChatPushService {
 private readonly http=inject(HttpClient);private readonly auth=inject(AuthService);private readonly api=inject(API_BASE_URL)+'/chat/push';
  private readonly testApi = inject(API_BASE_URL) + '/profile/push/test';
 readonly serviceWorkerSupported=typeof window!=='undefined'&&window.isSecureContext&&'serviceWorker' in navigator;
 readonly supported=this.serviceWorkerSupported&&'PushManager' in window&&'Notification' in window;
 readonly registered=signal(false);readonly enabled=signal(false);readonly busy=signal(false);readonly message=signal('');
  readonly testing = signal(false);
 readonly permission=signal<NotificationPermission|'unsupported'>(this.supported?Notification.permission:'unsupported');
 readonly serverReady=signal<boolean|null>(null);
 private options(){return {headers:new HttpHeaders({Authorization:'Bearer '+this.auth.accessToken()})};}
 async initialize():Promise<ServiceWorkerRegistration|null>{
  if(!this.serviceWorkerSupported)return null;
  try{const registration=await navigator.serviceWorker.register('/chat-push-sw.js',{scope:'/'});this.registered.set(true);return registration;}
  catch{this.registered.set(false);return null;}
 }
 async enable():Promise<void>{
  if(!this.supported||this.busy())return;
  this.busy.set(true);this.message.set('');
  try{
   // Permission request runs directly from the user's click.
   const permission=await Notification.requestPermission();this.permission.set(permission);
   if(permission!=='granted'){this.message.set($localize`:@@chatPushDenied:Benachrichtigungen sind nicht erlaubt. Sie können die Freigabe in den Browsereinstellungen ändern.`);return;}
   const config=await firstValueFrom(this.http.get<{publicKey:string|null}>(this.api,this.options()));
   this.serverReady.set(config.publicKey!==null);
   if(!config.publicKey){this.message.set($localize`:@@chatPushUnavailable:Push-Benachrichtigungen sind auf dem Server noch nicht eingerichtet.`);return;}
   const registration=await this.initialize();
   if(!registration)throw new Error('Service worker unavailable.');
   await navigator.serviceWorker.ready;
   const base64=config.publicKey.replace(/-/g,'+').replace(/_/g,'/');
   const key=Uint8Array.from(atob(base64.padEnd(Math.ceil(base64.length/4)*4,'=')),c=>c.charCodeAt(0));
   const subscription=await registration.pushManager.getSubscription()??await registration.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
   await firstValueFrom(this.http.post(this.api,subscription.toJSON(),this.options()));
   this.enabled.set(true);this.message.set($localize`:@@chatPushEnabled:Benachrichtigungen wurden aktiviert.`);
  }catch{this.message.set($localize`:@@chatPushFailed:Benachrichtigungen konnten nicht aktiviert werden. Bitte versuchen Sie es erneut.`);}finally{this.busy.set(false);}
 }
 async check():Promise<void>{
  this.enabled.set(false);this.serverReady.set(null);if(!this.supported){this.permission.set('unsupported');return;}
  this.permission.set(Notification.permission);
  try{
   const config=await firstValueFrom(this.http.get<{publicKey:string|null}>(this.api,this.options()));
   this.serverReady.set(config.publicKey!==null);
   if(!config.publicKey)return;
   const registration=await this.initialize();const subscription=await registration?.pushManager.getSubscription();
   if(subscription){const result=await firstValueFrom(this.http.post<{subscribed:boolean}>(this.api+'/status',{endpoint:subscription.endpoint},this.options()));this.enabled.set(result.subscribed);}
  }catch{this.enabled.set(false);this.serverReady.set(null);}
 }
 async disable():Promise<void>{
  if(!this.supported)return;this.busy.set(true);
  try{const registration=await navigator.serviceWorker.getRegistration('/');const sub=await registration?.pushManager.getSubscription();
   if(sub){await sub.unsubscribe();await firstValueFrom(this.http.delete(this.api,{...this.options(),body:{endpoint:sub.endpoint}}));}
   this.enabled.set(false);this.message.set($localize`:@@chatPushDisabled:Benachrichtigungen wurden deaktiviert.`);
  }catch{this.enabled.set(false);this.message.set($localize`:@@chatPushDisableFailed:Bitte prüfen Sie die Benachrichtigungsfreigabe in den Browsereinstellungen.`);}finally{this.busy.set(false);}
 }
  async sendTest(): Promise<void> {
    if (!this.enabled() || this.testing()) return;
    this.testing.set(true);
    this.message.set('');
    try {
      const result = await firstValueFrom(this.http.post<{ success: boolean; subscriptions: number }>(this.testApi, {}, this.options()));
      const devices = result.subscriptions === 1 ? 'ein registriertes Gerät' : `${result.subscriptions} registrierte Geräte`;
      this.message.set($localize`:@@chatPushTestSent:Die Test-Benachrichtigung wurde an ${devices} gesendet.`);
    } catch {
      this.message.set($localize`:@@chatPushTestFailed:Die Test-Benachrichtigung konnte nicht zugestellt werden. Bitte prüfen Sie die Gerätefreigabe und versuchen Sie es erneut.`);
    } finally {
      this.testing.set(false);
    }
  }
}
