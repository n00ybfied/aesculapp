import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface Conversation { id:number; subject:string; unreadCount:number; status:'open'|'closed'; customerName:string; createdAt:string; updatedAt:string; }
export interface ChatMessage { id:number; role:'staff'|'customer'; text:string; hasImage:boolean; createdAt:string; }
export interface ChatList { activeConversationId:number|null; conversations:Conversation[]; total:number; page:number; consentVersion:string; consentText:string; notice:string; consented:boolean; }
export interface ChatDetail { conversation:Conversation; messages:ChatMessage[]; hasOlder:boolean; }
@Injectable({providedIn:'root'})
export class ChatService {
 readonly openCount=signal<number|null>(null);
 private countPending=false;
 async refreshOpenCount():Promise<void>{
  if(this.countPending)return;
  this.countPending=true;
  try{const result=await firstValueFrom(this.http.get<{count:number}>(this.api+'/open-count',this.options()));this.openCount.set(result.count);}
  catch{this.openCount.set(null);}
  finally{this.countPending=false;}
 }
 private readonly http=inject(HttpClient);
 private readonly auth=inject(AdminAuthService);
 private readonly api=(['localhost','127.0.0.1'].includes(location.hostname)?'http://localhost:6080/api/v1':'https://api.aesculapp.floatbox.at/api/v1') + '/admin/chat';
 private options(){return {headers:new HttpHeaders({Authorization:'Bearer '+this.auth.accessToken()})};}
 list(page=1){return firstValueFrom(this.http.get<ChatList>(this.api+'?page='+page,this.options()));}
 read(id:number,before=0){return firstValueFrom(this.http.get<ChatDetail>(this.api+'/'+id+'?before='+before,this.options()));}
 async markRead(id:number,lastMessageId:number):Promise<void>{await firstValueFrom(this.http.post(this.api+'/'+id+'/read',{lastMessageId},this.options()));await this.refreshOpenCount();}
 send(data:FormData){return firstValueFrom(this.http.post<{conversationId:number}>(this.api+'/send',data,this.options()));}
 image(chat:number,message:number){return firstValueFrom(this.http.get(this.api+'/'+chat+'/images/'+message,{...this.options(),responseType:'blob'}));}
 async close(id:number){const result=await firstValueFrom(this.http.post(this.api+'/'+id+'/close',{},this.options()));await this.refreshOpenCount();return result;}
}
