import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminGame, AdminGamesService, GamePrize, GameWin } from '../../core/games/admin-games.service';
import { ConfirmDialogService } from '../../shared/confirm-dialog.service';

interface GameDraft {
  title: string;
  isVisible: boolean;
  availableFrom: string;
  availableUntil: string;
  participationMode: 'once' | 'total' | 'daily';
  spinLimit: number;
  noWinEnabled: boolean;
  soldOutText: string;
  prizes: GamePrize[];
}

function localInput(date: Date): string {
  const pad = (number: number) => String(number).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

@Component({
  selector: 'app-game-editor',
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './game-editor.component.html',
  styleUrl: './game-editor.component.css',
})
export class GameEditorComponent {
  private readonly service = inject(AdminGamesService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly dialogs = inject(ConfirmDialogService);
  protected readonly gameId = Number(this.route.snapshot.paramMap.get('id')) || null;
  protected readonly loading = signal(this.gameId !== null);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly notice = signal('');
  protected readonly wins = signal<readonly GameWin[]>([]);
  protected readonly hasSpins = signal(false);
  protected readonly game = signal<AdminGame | null>(null);
  protected redemptionCodes: Record<number, string> = {};
  protected draft: GameDraft = {
    title: '', isVisible: false, availableFrom: localInput(new Date()),
    availableUntil: localInput(new Date(Date.now() + 28 * 86400000)),
    participationMode: 'once', spinLimit: 1, noWinEnabled: true,
    soldOutText: 'Alle Gewinne sind vergeben. Vielen Dank fürs Mitmachen!',
    prizes: [{ title: '', wheelLabel: '', kind: 'points', points: 100, probabilityBp: 5000, stockLimit: null }],
  };

  constructor() {
    if (this.gameId !== null) void this.load();
  }

  protected get totalProbability(): number {
    return this.draft.prizes.reduce((sum, prize) => sum + (Number(prize.probabilityBp) || 0), 0);
  }

  protected get noWinProbability(): number { return Math.max(0, 10000 - this.totalProbability); }

  protected addPrize(): void {
    this.draft.prizes.push({ title: '', wheelLabel: '', kind: 'coupon', points: 0, probabilityBp: 1000, stockLimit: null });
  }

  protected removePrize(index: number): void { this.draft.prizes.splice(index, 1); }

  protected setProbability(prize: GamePrize, value: number | string): void {
    prize.probabilityBp = Math.round(Number(value) * 100);
  }

  protected setKind(prize: GamePrize, value: string): void {
    prize.kind = value === 'coupon' ? 'coupon' : 'points';
    prize.points = prize.kind === 'coupon' ? 0 : 100;
  }

  protected setLimit(prize: GamePrize, value: number | string | null): void {
    prize.stockLimit = value === null || value === '' ? null : Number(value);
  }

  protected setParticipationMode(value: string): void {
    this.draft.participationMode = value === 'daily' ? 'daily' : value === 'total' ? 'total' : 'once';
    if (this.draft.participationMode === 'once') this.draft.spinLimit = 1;
  }

  protected async save(): Promise<void> {
    this.error.set(''); this.notice.set('');
    const from = new Date(this.draft.availableFrom);
    const until = new Date(this.draft.availableUntil);
    if (!this.draft.title.trim() || Number.isNaN(from.getTime()) || Number.isNaN(until.getTime()) || from >= until
      || this.draft.prizes.length === 0 || this.totalProbability > 10000
      || (!this.draft.noWinEnabled && this.totalProbability !== 10000)
      || (this.draft.noWinEnabled && this.totalProbability >= 10000)
      || this.draft.prizes.some((prize) => !prize.title.trim() || !prize.wheelLabel.trim() || prize.probabilityBp < 1
        || (prize.kind === 'points' && prize.points < 1) || (prize.stockLimit !== null && prize.stockLimit < 1))) {
      this.error.set('Bitte prüfen Sie Titel, Zeitraum, Gewinne und die Summe der Gewinnchancen.');
      document.querySelector('.form-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    if (this.draft.isVisible && !this.game()?.isVisible) {
      const confirmed = await this.dialogs.confirm('Das Glücksrad wird im eingestellten Zeitraum für Kunden sichtbar und spielbar. Sobald die erste Teilnahme erfolgt, sind Gewinnchancen und Teilnahmegrenzen gesperrt.', { title: 'Gewinnspiel freischalten', confirmLabel: 'Freischalten' });
      if (!confirmed) return;
    }
    this.saving.set(true);
    try {
      const saved = await this.service.save(this.gameId, {
        ...this.draft,
        title: this.draft.title.trim(),
        availableFrom: from.toISOString(),
        availableUntil: until.toISOString(),
        prizes: this.draft.prizes.map((prize) => ({
          title: prize.title.trim(), wheelLabel: prize.wheelLabel.trim(), kind: prize.kind,
          points: prize.kind === 'points' ? Number(prize.points) : 0,
          probabilityBp: Number(prize.probabilityBp), stockLimit: prize.stockLimit === null ? null : Number(prize.stockLimit),
        })),
      });
      if (this.gameId === null) {
        void this.router.navigate(['/gewinnspiele', saved.id]);
      } else {
        this.game.set(saved);
        this.notice.set('Gewinnspiel gespeichert.');
      }
    } catch (error: unknown) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
        ? error.error.message : 'Das Gewinnspiel konnte nicht gespeichert werden.');
    } finally { this.saving.set(false); }
  }

  protected async redeem(win: GameWin): Promise<void> {
    const code = (this.redemptionCodes[win.id] ?? '').trim().toUpperCase();
    if (!/^[A-F0-9]{16}$/.test(code)) { this.error.set('Bitte geben Sie den 16-stelligen Gewinncode des Kunden ein.'); return; }
    if (!await this.dialogs.confirm(`Gewinn „${win.title}“ für ${win.customer} als eingelöst markieren?`, { title: 'Gewinn einlösen', confirmLabel: 'Einlösen' })) return;
    try {
      await this.service.redeem(win.id, code);
      this.redemptionCodes[win.id] = '';
      await this.loadWins();
      this.notice.set('Gewinn wurde eingelöst.');
      this.error.set('');
    } catch (error: unknown) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
        ? error.error.message : 'Der Gewinn konnte nicht eingelöst werden.');
    }
  }

  private async load(): Promise<void> {
    if (this.gameId === null) return;
    try {
      const game = await this.service.get(this.gameId);
      this.game.set(game);
      this.draft = {
        title: game.title, isVisible: game.isVisible,
        availableFrom: localInput(new Date(game.availableFrom)),
        availableUntil: localInput(new Date(game.availableUntil)),
        participationMode: game.participationMode, spinLimit: game.spinLimit,
        noWinEnabled: game.noWinEnabled, soldOutText: game.soldOutText,
        prizes: game.prizes.map((prize) => ({ ...prize })),
      };
      await this.loadWins();
    } catch { this.error.set('Das Gewinnspiel konnte nicht geladen werden.'); }
    finally { this.loading.set(false); }
  }

  private async loadWins(): Promise<void> {
    if (this.gameId === null) return;
    const wins = await this.service.wins(this.gameId);
    this.wins.set(wins);
    this.hasSpins.set(wins.length > 0);
  }
}
