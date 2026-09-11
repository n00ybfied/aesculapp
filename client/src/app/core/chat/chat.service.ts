import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { API_BASE_URL } from '../api/api.config';
export interface Conversation { id:number; subject:string; unreadCount:number; status:'open'|'closed'; customerName:string; createdAt:string; updatedAt:string; }
export interface ChatMessage { id:number; role:'staff'|'customer'; text:string; hasImage:boolean; createdAt:string; }
export interface ChatList { activeConversationId:number|null; conversations:Conversation[]; total:number; page:number; consentVersion:string; consentText:string; notice:string; consented:boolean; }
export interface ChatDetail { conversation:Conversation; messages:ChatMessage[]; hasOlder:boolean; }
@Injectable({providedIn:'root'})
export class ChatService {
 readonly unreadCount=signal<number|null>(null);
 private countPending=false;
 async refreshUnreadCount():Promise<void>{
  if(this.countPending)return;
  this.countPending=true;
  try{const token=this.auth.accessToken();const result=await firstValueFrom(this.http.get<{count:number}>(this.api+'/unread-count',this.options()));if(token===this.auth.accessToken())this.unreadCount.set(result.count);}
  catch{this.unreadCount.set(null);}
  finally{this.countPending=false;}
 }
 async markRead(id:number,lastMessageId:number):Promise<void>{
  await firstValueFrom(this.http.post(this.api+'/'+id+'/read',{lastMessageId},this.options()));
  await this.refreshUnreadCount();
 }
 private readonly http=inject(HttpClient);
 private readonly auth=inject(AuthService);
 private readonly api=inject(API_BASE_URL) + '/chat';
 private options(){return {headers:new HttpHeaders({Authorization:'Bearer '+this.auth.accessToken()})};}
 list(page=1){return firstValueFrom(this.http.get<ChatList>(this.api+'?page='+page,this.options()));}
 read(id:number,before=0){return firstValueFrom(this.http.get<ChatDetail>(this.api+'/'+id+'?before='+before,this.options()));}
 send(data:FormData){return firstValueFrom(this.http.post<{conversationId:number}>(this.api+'/send',data,this.options()));}
 image(chat:number,message:number){return firstValueFrom(this.http.get(this.api+'/'+chat+'/images/'+message,{...this.options(),responseType:'blob'}));}
 close(id:number){return firstValueFrom(this.http.post(this.api+'/'+id+'/close',{},this.options()));}
}
