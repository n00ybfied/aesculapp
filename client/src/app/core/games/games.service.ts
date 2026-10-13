import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface GamePrize {
  id: number;
  title: string;
  wheelLabel: string;
  kind: 'points' | 'coupon';
  points: number;
  probabilityBp: number;
}

export interface CustomerGame {
  id: number;
  title: string;
  availableFrom: string;
  availableUntil: string;
  participationMode: 'once' | 'total' | 'daily';
  spinLimit: number;
  remainingSpins: number;
  noWinEnabled: boolean;
  soldOutText: string;
  status: 'active' | 'sold_out';
  prizes?: GamePrize[];
}

export interface GameSpin {
  id: number;
  gameId: number;
  gameTitle: string;
  wheelIndex: number | null;
  title: string;
  kind: 'none' | 'points' | 'coupon';
  points: number;
  couponCode: string | null;
  createdAt: string;
  redeemedAt: string | null;
}

@Injectable({ providedIn: 'root' })
export class GamesService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  list(): Promise<readonly CustomerGame[]> {
    return firstValueFrom(this.http.get<{ games: CustomerGame[] }>(`${this.api}/games`, this.options())).then((result) => result.games);
  }

  detail(id: number): Promise<{ game: CustomerGame; spins: GameSpin[] }> {
    return firstValueFrom(this.http.get<{ game: CustomerGame; spins: GameSpin[] }>(`${this.api}/games/${id}`, this.options()));
  }

  spin(id: number, requestId: string): Promise<{ game: CustomerGame; spin: GameSpin }> {
    return firstValueFrom(this.http.post<{ game: CustomerGame; spin: GameSpin }>(`${this.api}/games/${id}/spin`, { requestId }, this.options()));
  }

  wins(): Promise<readonly GameSpin[]> {
    return firstValueFrom(this.http.get<{ wins: GameSpin[] }>(`${this.api}/games/wins`, this.options())).then((result) => result.wins);
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }) };
  }
}
