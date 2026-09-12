import { Component, OnInit, OnDestroy, ElementRef, Injector, afterNextRender, inject, input, signal, viewChild } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ChatService, ChatList, ChatDetail, Conversation } from '../../core/chat/chat.service';

@Component({
 selector:'app-chat-image',
 template:`@if(url()){<a [href]="url()" target="_blank" rel="noopener" aria-label="Bild in voller Größe öffnen"><img [src]="url()" alt="Bildanhang zur Nachricht" /></a>}@else{<span>{{failed() ? 'Bild konnte nicht geladen werden.' : 'Bild wird geladen …'}}</span>}`,
 styles:[`img{display:block;max-width:100%;max-height:20rem;object-fit:contain;border-radius:.6rem}a{display:block}`]
})
export class ChatImageComponent implements OnInit,OnDestroy {
 readonly chat=input.required<number>(); readonly message=input.required<number>();
 private readonly service=inject(ChatService); readonly url=signal(''); readonly failed=signal(false); private destroyed=false;
 async ngOnInit(){try{const blob=await this.service.image(this.chat(),this.message());if(!this.destroyed)this.url.set(URL.createObjectURL(blob));}catch{this.failed.set(true);}}
 ngOnDestroy(){this.destroyed=true;if(this.url())URL.revokeObjectURL(this.url());}
}
@Component({
 selector:'app-chat-page', imports:[FormsModule,ChatImageComponent],
 template:`
 <main class="chat-page">
 <header><div><p class="eyebrow">IHRE APOTHEKE</p><h1>Chat</h1></div>@if(detail() || composing()){<button type="button" [disabled]="busy()" (click)="overview()">Gesprächsübersicht</button>}</header>
 <p class="test-notice">Testphase: Bitte ausschließlich fiktive Daten und keine echten Rezepte senden. Nicht für medizinische Notfälle geeignet.</p>
 @if(error()){<p role="alert" class="error">{{error()}}</p>}
 @if(loading()){<div role="status" class="loading"><span class="spinner"></span> Wird geladen …</div>}
 @else if(list(); as data){
  @if(!composing() && !detail()){
   <button class="primary new-chat-button" type="button" [disabled]="busy() || !!list()?.activeConversationId" (click)="newChat()">Neue Anfrage starten</button>
   @if(data.activeConversationId){<p class="test-notice">Sie haben bereits eine offene Anfrage. Bitte öffnen Sie das laufende Gespräch in der Liste.</p>}
   <h2>Ihre Gespräche</h2>
   @for(chat of data.conversations;track chat.id){
    <button class="conversation" type="button" (click)="open(chat.id)"><span>{{chat.subject}}<small>{{formatDate(chat.updatedAt)}}</small></span><span class="conversation-status">@if(chat.unreadCount){<span class="unread-badge" [attr.aria-label]="chat.unreadCount+' ungelesene Antworten'">{{chat.unreadCount}}</span>}<span class="status-badge" [class.status-open]="chat.status==='open'" [class.status-closed]="chat.status==='closed'">{{chat.status==='open'?'Offen':'Abgeschlossen'}}</span></span></button>
   }@empty{<p>Noch keine Gespräche vorhanden.</p>}
   <nav class="pagination" aria-label="Gesprächsseiten"><button type="button" [disabled]="data.page<=1" (click)="loadList(data.page-1)">Zurück</button><span>Seite {{data.page}}</span><button type="button" [disabled]="data.page*20>=data.total" (click)="loadList(data.page+1)">Weiter</button></nav>
  }@else{
   @if(detail();as current){
    <div class="conversation-heading"><h2>{{current.conversation.subject}}</h2></div>
    @if(hasOlder()){<button type="button" [disabled]="busy()" (click)="older()">Ältere Nachrichten laden</button>}
   }
   <section class="messages" aria-label="Nachrichten">
    <div class="bubble"><p>Wie können wir Ihnen heute helfen?</p><small>Automatische Begrüßung</small></div>
    @for(message of detail()?.messages ?? [];track message.id){
     <article class="bubble" [class.own]="message.role==='customer'">
      <small>{{message.role==='staff'?'Apothekenteam':'Kunde'}}</small>
      @if(message.text){<p>{{message.text}}</p>}
      @if(message.hasImage){<app-chat-image [chat]="detail()!.conversation.id" [message]="message.id" />}
      <time>{{formatDate(message.createdAt)}}</time>
     </article>
    }
    <div #messageEnd class="message-end" aria-hidden="true"></div>
   </section>
   @if(detail()?.conversation?.status==='closed'){
    <div class="closed"><p>Dieses Gespräch wurde abgeschlossen. Sie können den Verlauf weiterhin lesen.</p><button class="primary" type="button" [disabled]="busy() || !!list()?.activeConversationId" (click)="newChat()">Neue Anfrage starten</button></div>
   }@else{
    
    @if(!data.consented){
     <section class="consent"><h2>Datenschutz im Chat</h2><p>{{data.notice}}</p>
      <details><summary>Datenschutzhinweise zum Testchat</summary><p>Ihre Apotheke verarbeitet Ihre Nachrichten und Bilder zur Bearbeitung Ihrer Anfrage. Nur Sie und berechtigte Mitarbeiter Ihrer Apotheke erhalten Zugriff über die App. Es werden keine Chatinhalte in Benachrichtigungen versendet. Für diese Testphase dürfen keine echten Gesundheitsdaten verwendet werden. Die endgültigen Aufbewahrungsfristen und Kontaktangaben zum Widerruf müssen vor dem Echtbetrieb ergänzt werden.</p><p>Version: {{data.consentVersion}}</p></details>
      <label><input type="checkbox" [(ngModel)]="consent" [disabled]="busy()" /> <span>{{data.consentText}}</span></label>
     </section>
    }
    <form class="composer" (ngSubmit)="send()">
     @if(!detail()){<label for="chat-subject">Betreff (optional)</label><input class="subject-input" id="chat-subject" name="subject" maxlength="160" [(ngModel)]="subject" [disabled]="busy()" placeholder="Worum geht es?" />}
     <label for="chat-text">Ihre Nachricht</label>
     <textarea id="chat-text" name="text" rows="3" maxlength="5000" [(ngModel)]="draft" [disabled]="busy()" placeholder="Nachricht schreiben …"></textarea>
     @if(preview()){<div class="preview"><img [src]="preview()" alt="Ausgewählter Bildanhang" /><button type="button" [disabled]="busy()" (click)="removeImage()">Bild entfernen</button></div>}
     <div class="composer-actions"><label class="upload"><span>Bild hinzufügen</span><input type="file" accept="image/jpeg,image/png,image/webp" [disabled]="busy()" (change)="pick($event)" /></label><button class="primary" type="submit" [disabled]="busy() || (!draft.trim() && !file) || (!data.consented && !consent)">{{busy()?'Wird gesendet …':'Senden'}}</button></div>
     <small>JPEG, PNG oder WebP · maximal 5 MB · 16 Megapixel</small>
    </form>
   }
  }
 }
 </main>`,
 styles:[`
 .message-end{scroll-margin-bottom:8rem}
 .test-notice{margin-bottom:1.25rem}.new-chat-button{margin-bottom:1.25rem}
 .unread-badge{display:inline-block;padding:.15rem .5rem;background:var(--color-danger);color:white;border-radius:999px;font-size:.75rem}
 .subject-input{display:block;box-sizing:border-box;width:100%;padding:.75rem;margin:.5rem 0 1rem;border:1px solid var(--chat-border);border-radius:.6rem;background:var(--chat-surface);color:inherit;font:inherit}
 .primary:disabled{background:var(--chat-border);border-color:var(--chat-border);color:var(--chat-muted);opacity:1}
 .conversation>span:first-child{min-width:0;overflow-wrap:anywhere}
 .closed p{margin:0 0 1.25rem}
 :host{--chat-surface:var(--color-surface);--chat-primary:var(--color-primary);--chat-border:var(--color-border);--chat-muted:var(--color-muted);display:block}
 .chat-page{max-width:58rem;margin:auto;padding:1.25rem}header,.conversation-heading,.composer-actions,.pagination{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap}h1{margin:.2rem 0 1rem;font-size:1.8rem}h2{font-size:1.15rem;margin:1rem 0}.eyebrow,small,time{font-size:.8rem;color:var(--chat-muted)}button,.upload{cursor:pointer;min-height:44px;padding:.65rem .9rem;border:1px solid var(--chat-border);border-radius:.65rem;background:var(--chat-surface);color:inherit;font:inherit}button:disabled{cursor:not-allowed;opacity:.55}.primary{background:var(--chat-primary);color:white;border-color:var(--chat-primary)}button:focus-visible,textarea:focus-visible,input:focus-visible,summary:focus-visible{outline:3px solid var(--chat-primary);outline-offset:3px}
 .test-notice{font-size:.85rem;color:var(--chat-muted);line-height:1.5}.error{padding:1rem;border:1px solid currentColor;color:var(--color-danger);border-radius:.75rem}.conversation{display:flex;justify-content:space-between;gap:1rem;width:100%;text-align:left;margin:.6rem 0}.conversation-status{display:flex;align-items:center;gap:.5rem;white-space:nowrap}.status-badge{display:inline-flex;min-height:1.75rem;align-items:center;border-radius:999px;padding:0 .65rem;font-size:.8rem;font-weight:700}.status-open{background:color-mix(in srgb,var(--color-success) 14%,var(--chat-surface));color:var(--color-success)}.status-closed{background:color-mix(in srgb,var(--color-danger) 12%,var(--chat-surface));color:var(--color-danger)}.conversation small{display:block;margin-top:.4rem}.messages{display:flex;flex-direction:column;gap:1rem;margin:1rem 0}.bubble{max-width:90%;align-self:flex-start;background:var(--chat-surface);border:1px solid var(--chat-border);padding:.85rem 1rem;border-radius:1rem;overflow-wrap:anywhere}.bubble.own{align-self:flex-end;border-color:var(--chat-primary);background:color-mix(in srgb,var(--chat-primary) 12%,var(--chat-surface))}.bubble p{white-space:pre-wrap;margin:.4rem 0}.bubble time{display:block;margin-top:.5rem}.composer,.consent,.closed{background:var(--chat-surface);padding:1rem;border:1px solid var(--chat-border);border-radius:1rem;margin-top:1rem}.composer textarea{display:block;box-sizing:border-box;width:100%;resize:vertical;padding:.75rem;border:1px solid var(--chat-border);border-radius:.6rem;background:var(--chat-surface);color:inherit;font:inherit;margin:.5rem 0}.consent{font-size:.9rem;line-height:1.6}.consent label{display:flex;gap:.75rem;margin-top:1rem;align-items:flex-start}.consent input{width:24px;height:24px;flex-shrink:0;accent-color:var(--chat-primary)}summary{cursor:pointer;text-decoration:underline}.upload{position:relative;overflow:hidden}.upload input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}.upload:focus-within{outline:3px solid var(--chat-primary)}.preview{display:flex;gap:1rem;align-items:center;margin:.75rem 0}.preview img{max-width:7rem;max-height:7rem;object-fit:contain}.loading{min-height:15rem;display:grid;place-content:center;justify-items:center;gap:1rem}.spinner{width:2rem;height:2rem;border:3px solid var(--chat-border);border-top-color:var(--chat-primary);border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.spinner{animation:none}}
 `]
})
export class ChatPage implements OnInit,OnDestroy {
 private lastRead=0;
 private readonly visibilityChanged=()=>{if(!document.hidden){this.acknowledge();void this.poll();}};
 private acknowledge():void{
  const current=this.detail();const last=current?.messages.at(-1)?.id;
  if(!current||!last||last<=this.lastRead||document.hidden)return;
  afterNextRender(()=>{if(this.destroyed||document.hidden||this.detail()?.conversation.id!==current.conversation.id)return;
   void this.service.markRead(current.conversation.id,last).then(()=>{this.lastRead=Math.max(this.lastRead,last);this.list.update(list=>list?{...list,conversations:list.conversations.map(chat=>chat.id===current.conversation.id?{...chat,unreadCount:0}:chat)}:list);}).catch(()=>undefined);
  },{injector:this.injector});
 }
 private readonly service=inject(ChatService);
 private readonly injector=inject(Injector);
 private readonly messageEnd=viewChild<ElementRef<HTMLElement>>('messageEnd');
 private scrollToLatest():void {
  afterNextRender(()=>{if(!this.destroyed)this.messageEnd()?.nativeElement.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'end'});},{injector:this.injector});
 }
 readonly list=signal<ChatList|null>(null); readonly detail=signal<ChatDetail|null>(null);
 readonly loading=signal(true); readonly busy=signal(false); readonly error=signal(''); readonly composing=signal(false); readonly hasOlder=signal(false); readonly preview=signal('');
 subject=''; draft=''; consent=false; file:File|null=null; private requestId=''; private retryBody:FormData|null=null;
 private timer:ReturnType<typeof setInterval>|undefined; private destroyed=false; private generation=0; private polling=false;
 async ngOnInit(){await this.loadList();if(this.destroyed)return;document.addEventListener('visibilitychange',this.visibilityChanged);this.timer=setInterval(()=>void this.poll(),5000);}
 ngOnDestroy(){document.removeEventListener('visibilitychange',this.visibilityChanged);this.destroyed=true;this.generation++;if(this.timer)clearInterval(this.timer);this.removeImage();}
 formatDate(value:string){return new Intl.DateTimeFormat('de-AT',{dateStyle:'short',timeStyle:'short'}).format(new Date(value));}
 private failure(error:unknown){this.error.set(error instanceof HttpErrorResponse && error.status===409?'Dieses Gespräch wurde inzwischen abgeschlossen.':error instanceof HttpErrorResponse && error.status===422?'Bitte prüfen Sie Ihre Nachricht, Zustimmung und das Bildformat (maximal 5 MB / 16 Megapixel).':'Der Chat konnte nicht aktualisiert werden. Bitte versuchen Sie es erneut.');}
 async loadList(page=1){this.loading.set(true);try{const result=await this.service.list(page);if(!this.destroyed)this.list.set(result);}catch(e){this.failure(e);}finally{this.loading.set(false);}}
 async overview(){if(this.busy())return;this.generation++;this.detail.set(null);this.composing.set(false);await this.loadList();}
 async newChat(){if(this.busy()||this.list()?.activeConversationId)return;await this.loadList();if(this.error()||this.list()?.activeConversationId)return;this.subject='';this.detail.set(null);this.composing.set(true);this.draft='';this.removeImage();this.retryBody=null;}
 async open(id:number){if(this.busy())return;const version=++this.generation;this.loading.set(true);this.error.set('');try{const result=await this.service.read(id);if(version===this.generation&&!this.destroyed){this.lastRead=0;this.detail.set(result);this.acknowledge();this.hasOlder.set(result.hasOlder);this.composing.set(false);this.draft='';this.removeImage();this.retryBody=null;}}catch(e){this.failure(e);}finally{if(version===this.generation)this.loading.set(false);}}
 private async poll(){const current=this.detail();if(!current||current.conversation.status!=='open'||document.hidden||this.busy()||this.loading()||this.polling||this.destroyed)return;const version=this.generation;this.polling=true;try{const next=await this.service.read(current.conversation.id);if(version===this.generation&&!this.destroyed){const merged=new Map(current.messages.map(m=>[m.id,m]));next.messages.forEach(m=>merged.set(m.id,m));this.detail.set({...next,messages:[...merged.values()].sort((a,b)=>a.id-b.id)});this.acknowledge();this.list.update(list=>list?{...list,activeConversationId:next.conversation.status==='open'?next.conversation.id:null}:list);}}catch(e){if(!this.destroyed)this.failure(e);}finally{this.polling=false;}}
 async older(){const current=this.detail();if(!current||this.busy())return;this.busy.set(true);try{const old=await this.service.read(current.conversation.id,current.messages[0]?.id);this.detail.set({...current,messages:[...old.messages,...current.messages]});this.hasOlder.set(old.hasOlder);}catch(e){this.failure(e);}finally{this.busy.set(false);}}
 pick(event:Event){const input=event.target as HTMLInputElement;const file=input.files?.[0];input.value='';if(!file)return;if(file.size>5*1024*1024||!['image/jpeg','image/png','image/webp'].includes(file.type)){this.error.set('Bitte ein JPEG-, PNG- oder WebP-Bild bis 5 MB wählen.');return;}this.removeImage();this.file=file;this.preview.set(URL.createObjectURL(file));this.retryBody=null;}
 removeImage(){if(this.preview())URL.revokeObjectURL(this.preview());this.preview.set('');this.file=null;}
 async send(){
  if(this.busy()||(!this.draft.trim()&&!this.file))return;
  if(!this.list()?.consented&&!this.consent)return;
  this.busy.set(true);this.error.set('');this.generation++;
  const body=new FormData();body.set('text',this.draft.trim());body.set('subject',this.subject.trim());body.set('conversationId',String(this.detail()?.conversation.id??0));body.set('consent',String(this.consent));body.set('consentVersion',this.list()?.consentVersion??'');if(this.file)body.set('image',this.file);
  if(!this.retryBody||this.retryBody.get('subject')!==body.get('subject')||this.retryBody.get('text')!==body.get('text')||this.retryBody.get('conversationId')!==body.get('conversationId'))this.requestId=crypto.randomUUID();
  body.set('requestId',this.requestId);this.retryBody=body;
  try{
   const result=await this.service.send(body);
   const next=await this.service.read(result.conversationId);
   if(this.destroyed)return;
   const current=this.detail();
   const merged=new Map((current?.messages??[]).map(message=>[message.id,message]));
   next.messages.forEach(message=>merged.set(message.id,message));
   this.detail.set({...next,messages:[...merged.values()].sort((a,b)=>a.id-b.id)});this.acknowledge();
   if(!current)this.hasOlder.set(next.hasOlder);
   this.list.update(list=>list?{...list,consented:true,activeConversationId:next.conversation.status==='open'?next.conversation.id:null}:list);
   this.composing.set(false);this.draft='';this.removeImage();this.retryBody=null;
   this.scrollToLatest();
  }
  catch(e){this.failure(e);if(e instanceof HttpErrorResponse&&e.status===409){this.busy.set(false);await this.poll();}}finally{this.busy.set(false);}
 }
 async close(){const id=this.detail()?.conversation.id;if(!id||this.busy()||!confirm('Dieses Gespräch wirklich abschließen?'))return;this.busy.set(true);this.generation++;try{await this.service.close(id);this.busy.set(false);await this.open(id);}catch(e){this.failure(e);}finally{this.busy.set(false);}}
}
