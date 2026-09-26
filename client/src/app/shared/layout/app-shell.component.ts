import { Component, OnInit, OnDestroy, computed, inject, signal } from '@angular/core';
import { ChatService } from '../../core/chat/chat.service';
import { ChatPushService } from '../../core/chat/chat-push.service';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { AuthService } from '../../core/auth/auth.service';
import { FooterNavigationItem, ProfileService } from '../../core/profile/profile.service';
import { ThemeService } from '../../core/theme/theme.service';
import { SnackbarComponent } from '../feedback/snackbar.component';
import { AnalyticsService } from '../../core/analytics/analytics.service';
import { ConfirmDialogComponent } from '../feedback/confirm-dialog.component';

interface FooterNavigationLink {
  readonly id: FooterNavigationItem;
  readonly label: string;
  readonly route: string;
  readonly icon: string;
}

const FOOTER_NAVIGATION_LINKS: readonly FooterNavigationLink[] = [
  { id: 'home', label: 'Home', route: '/dashboard', icon: 'lucideHouse' },
  { id: 'chat', label: 'Chat', route: '/chat', icon: 'lucideMessageCircle' },
  { id: 'rewards', label: 'Prämien', route: '/punkte', icon: 'lucideGem' },
  { id: 'coupons', label: 'Gutscheine', route: '/gutscheine', icon: 'lucideTicket' },
  { id: 'news', label: 'News', route: '/news', icon: 'lucideNewspaper' },
  { id: 'appointments', label: 'Termine', route: '/termine', icon: 'lucideCalendarDays' },
  { id: 'medications', label: 'Medikamente', route: '/medikamente', icon: 'lucidePill' },
  { id: 'family', label: 'Familie', route: '/familie', icon: 'lucideUsers' },
  { id: 'contact', label: 'Kontakt', route: '/kontakt', icon: 'lucideMapPin' },
  { id: 'website', label: 'Webseite', route: '/webseite', icon: 'lucideGlobe' },
  { id: 'achievements', label: 'Trophäen', route: '/trophaeen', icon: 'lucideTrophy' },
];

@Component({
  selector: 'app-shell',
  imports: [NgIcon, RouterLink, RouterLinkActive, RouterOutlet, SnackbarComponent, ConfirmDialogComponent],
  templateUrl: './app-shell.component.html',
})
export class AppShellComponent implements OnInit,OnDestroy {
  private readonly chats=inject(ChatService);
  private readonly push=inject(ChatPushService);
  protected readonly unreadReplies=this.chats.unreadCount;
  private countTimer:ReturnType<typeof setInterval>|undefined;
  private readonly refreshChatBadge=()=>{if(!document.hidden)void this.chats.refreshUnreadCount();};
  ngOnDestroy():void{if(this.countTimer)clearInterval(this.countTimer);document.removeEventListener('visibilitychange',this.refreshChatBadge);this.analytics.stopSession();this.chats.unreadCount.set(null);this.clearNavigationTimers();}
  private readonly navigationTransitionMs = 220;
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly analytics = inject(AnalyticsService);
  private readonly profiles = inject(ProfileService);
  protected readonly theme = inject(ThemeService).activeTheme;
  protected readonly profile = this.profiles.profile;
  protected readonly footerNavigationItems = computed(() => {
    const selected = this.profile()?.footerNavigationItems ?? ['home', 'chat', 'rewards', 'website'];
    return [...new Set(selected.map((item) => item === 'my-appointments' ? 'appointments' : item))]
      .filter((item) => item !== 'website' || this.theme.websiteUrl !== null)
      .map((item) => FOOTER_NAVIGATION_LINKS.find((link) => link.id === item))
      .filter((item): item is FooterNavigationLink => item !== undefined);
  });
  protected readonly leftFooterNavigationItems = computed(() => this.footerNavigationItems().slice(0, Math.ceil(this.footerNavigationItems().length / 2)));
  protected readonly rightFooterNavigationItems = computed(() => this.footerNavigationItems().slice(Math.ceil(this.footerNavigationItems().length / 2)));

  protected readonly navigationOpen = signal(false);
  protected readonly navigationVisible = signal(false);
  protected readonly pushPromptOpen = signal(false);
  protected readonly appNoticeOpen = signal(false);
  private pushPromptKey: string | null = null;
  private navigationExitTimer: ReturnType<typeof setTimeout> | undefined;
  private navigationEnterTimer: ReturnType<typeof setTimeout> | undefined;

  async ngOnInit(): Promise<void> {
    this.analytics.startSession();
    void this.initializePushPrompt();
    this.refreshChatBadge();
    this.countTimer=setInterval(this.refreshChatBadge,5000);
    document.addEventListener('visibilitychange',this.refreshChatBadge);
    try {
      await this.profiles.load();
    } catch {
      // The profile page presents a detailed retry message; the shell keeps its initials fallback.
    }
    this.initializeAppNotice();
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

  protected navigateAndClose(path: string, event: MouseEvent): void {
    event.preventDefault();
    this.closeNavigation();
    void this.router.navigateByUrl(path);
  }

  protected async logout(): Promise<void> {
    await this.push.disable();
    this.authService.logout();
    this.closeNavigation();
    void this.router.navigate(['/login']);
  }

  protected dismissPushPrompt(): void {
    this.rememberPushPrompt();
    this.pushPromptOpen.set(false);
  }

  protected dismissAppNotice(): void { this.appNoticeOpen.set(false); }

  protected hideAppNoticeForThirtyDays(): void {
    const user = this.authService.currentUser();
    const title = this.theme.appNoticeTitle ?? '';
    if (user !== null) {
      try { localStorage.setItem(this.appNoticeKey(user.id), JSON.stringify({ until: Date.now() + 30 * 24 * 60 * 60 * 1000, signature: `${title}|${this.theme.appNoticeHtml}` })); } catch { /* Private mode may block storage. */ }
    }
    this.appNoticeOpen.set(false);
  }

  protected enablePushFromPrompt(): void {
    this.rememberPushPrompt();
    this.pushPromptOpen.set(false);
    void this.push.enable();
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

  private async initializePushPrompt(): Promise<void> {
    await this.push.initialize();
    await this.push.check();
    const user = this.authService.currentUser();
    if (!this.push.supported || this.push.enabled() || Notification.permission !== 'default' || user === null) return;
    this.pushPromptKey = `aesculapp.push-prompt.v1.${user.id}`;
    try { if (localStorage.getItem(this.pushPromptKey) !== null) return; } catch { return; }
    this.pushPromptOpen.set(true);
  }

  private initializeAppNotice(): void {
    const user = this.authService.currentUser();
    if (!this.theme.appNoticeEnabled || this.theme.appNoticeTitle === null || this.theme.appNoticeHtml === '' || user === null) return;
    try {
      const stored = JSON.parse(localStorage.getItem(this.appNoticeKey(user.id)) ?? 'null') as { until?: unknown; signature?: unknown } | null;
      const signature = `${this.theme.appNoticeTitle}|${this.theme.appNoticeHtml}`;
      if (stored !== null && stored.signature === signature && typeof stored.until === 'number' && stored.until > Date.now()) return;
    } catch { /* An unreadable value must never hide an important pharmacy notice. */ }
    this.appNoticeOpen.set(true);
  }

  private appNoticeKey(userId: number): string { return `aesculapp.app-notice.v1.${this.theme.id}.${userId}`; }

  private rememberPushPrompt(): void {
    if (this.pushPromptKey === null) return;
    try { localStorage.setItem(this.pushPromptKey, 'seen'); } catch { /* Private mode may block storage. */ }
  }

  private clearTimer(timer: ReturnType<typeof setTimeout> | undefined): void {
    if (timer !== undefined) {
      clearTimeout(timer);
    }
  }
}
