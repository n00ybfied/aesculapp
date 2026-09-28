import { Component, OnInit, inject, signal } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { ReferralService, type ReferralOverview } from '../../core/referrals/referral.service';

@Component({
  selector: 'app-referrals-page',
  imports: [NgIcon],
  templateUrl: './referrals.page.html',
})
export class ReferralsPage implements OnInit {
  private readonly referrals = inject(ReferralService);
  protected readonly overview = signal<ReferralOverview | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly message = signal('');
  protected readonly canShare = this.referrals.canShare();

  ngOnInit(): void {
    void this.load();
  }

  protected async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(false);
    try {
      this.overview.set(await this.referrals.getOverview());
    } catch {
      this.error.set(true);
    } finally {
      this.loading.set(false);
    }
  }

  protected async copyLink(url: string): Promise<void> {
    try {
      await this.referrals.copyLink(url);
      this.message.set('Einladungslink kopiert.');
    } catch {
      this.message.set('Kopieren ist hier nicht verfügbar. Bitte markieren und kopieren Sie den Link im Feld.');
    }
  }

  protected selectLink(event: FocusEvent): void {
    (event.target as HTMLInputElement).select();
  }

  protected async shareLink(url: string): Promise<void> {
    try {
      await this.referrals.shareLink(url);
    } catch {
      // Das Schließen des nativen Teilen-Dialogs ist keine Fehlermeldung.
    }
  }
}
