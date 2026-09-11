import { Component, inject } from '@angular/core';
import { ChatService } from '../core/chat/chat.service';

@Component({
 selector:'app-open-chat-badge',
 template:`@if(count(); as count){<span [attr.aria-label]="count + ' offene Anfragen'">{{count}}</span>}`,
 styles:[`
  :host{display:inline-flex;vertical-align:middle}
  span{display:inline-flex;align-items:center;justify-content:center;min-width:1.5rem;height:1.5rem;padding:0 .4rem;border-radius:999px;background:var(--admin-danger);color:white;font-size:.75rem;font-weight:700;line-height:1;white-space:nowrap}
 `],
})
export class OpenChatBadgeComponent {
 protected readonly count=inject(ChatService).openCount;
}
