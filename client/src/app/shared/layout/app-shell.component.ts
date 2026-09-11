import { Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { ChatService } from '../../core/chat/chat.service';
import { ChatPushService } from '../../core/chat/chat-push.service';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { AuthService } from '../../core/auth/auth.service';
import { ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { SnackbarComponent } from '../feedback/snackbar.component';

@Component({
  selector: 'app-shell',
  imports: [NgIcon, RouterLink, RouterLinkActive, RouterOutlet, SnackbarComponent],
  templateUrl: './app-shell.component.html',
})
export class AppShellComponent implements OnInit,OnDestroy {
  private readonly chats=inject(ChatService);
  private readonly push=inject(ChatPushService);
  protected readonly unreadReplies=this.chats.unreadCount;
  private countTimer:ReturnType<typeof setInterval>|undefined;
  private readonly refreshChatBadge=()=>{if(!document.hidden)void this.chats.refreshUnreadCount();};
  ngOnDestroy():void{if(this.countTimer)clearInterval(this.countTimer);document.removeEventListener('visibilitychange',this.refreshChatBadge);this.chats.unreadCount.set(null);this.clearNavigationTimers();}
  private readonly navigationTransitionMs = 220;
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly profiles = inject(ProfileService);
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly profile = this.profiles.profile;

  protected readonly navigationOpen = signal(false);
  protected readonly navigationVisible = signal(false);
  private navigationExitTimer: ReturnType<typeof setTimeout> | undefined;
  private navigationEnterTimer: ReturnType<typeof setTimeout> | undefined;

  async ngOnInit(): Promise<void> {
    this.refreshChatBadge();
    this.countTimer=setInterval(this.refreshChatBadge,5000);
    document.addEventListener('visibilitychange',this.refreshChatBadge);
    try {
      await this.profiles.load();
    } catch {
      // The profile page presents a detailed retry message; the shell keeps its initials fallback.
    }
  }

  protected openNavigation(): void {
    this.clearNavigationTimers();
    this.navigationVisible.set(true);
    this.navigationOpen.set(false);
    this.navigationEnterTimer = setTimeout(() => this.navigationOpen.set(true));
  }

  protected closeNavigation(): void {
    this.navigationOpen.set(false);
    this.clearTimer(this.navigationEnterTimer);
    this.navigationEnterTimer = undefined;
    this.clearTimer(this.navigationExitTimer);
    this.navigationExitTimer = setTimeout(() => this.navigationVisible.set(false), this.navigationTransitionMs);
  }

  protected async logout(): Promise<void> {
    await this.push.disable();
    this.authService.logout();
    this.closeNavigation();
    void this.router.navigate(['/login']);
  }

  protected profileInitials(): string {
    const name = this.profile()?.displayName ?? this.authService.currentUser()?.displayName ?? 'K';
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
  }

  private clearNavigationTimers(): void {
    this.clearTimer(this.navigationEnterTimer);
    this.clearTimer(this.navigationExitTimer);
    this.navigationEnterTimer = undefined;
    this.navigationExitTimer = undefined;
  }

  private clearTimer(timer: ReturnType<typeof setTimeout> | undefined): void {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  }
}
