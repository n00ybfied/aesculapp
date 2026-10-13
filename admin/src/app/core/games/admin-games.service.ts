import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface GamePrize {
  id?: number;
  title: string;
  wheelLabel: string;
  kind: 'points' | 'coupon';
  points: number;
  probabilityBp: number;
  stockLimit: number | null;
  awardedCount?: number;
}

export interface AdminGame {
  id: number;
  title: string;
  isVisible: boolean;
  availableFrom: string;
  availableUntil: string;
  participationMode: 'once' | 'total' | 'daily';
  spinLimit: number;
  noWinEnabled: boolean;
  soldOutText: string;
  soldOutAt: string | null;
  debugEditAllowed?: boolean;
  status: 'draft' | 'scheduled' | 'active' | 'sold_out' | 'ended';
  prizes: GamePrize[];
}

export interface GameWin {
  id: number;
  title: string;
  kind: 'none' | 'points' | 'coupon';
  points: number;
  createdAt: string;
  redeemedAt: string | null;
  customer: string;
  redeemedBy: string | null;
}

@Injectable({ providedIn: 'root' })
export class AdminGamesService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1'
    : 'https://api.aesculapp.floatbox.at/api/v1';

  list(): Promise<readonly AdminGame[]> {
    return firstValueFrom(this.http.get<{ games: AdminGame[] }>(`${this.api}/admin/games`, this.options())).then((result) => result.games);
  }

  get(id: number): Promise<AdminGame> {
    return firstValueFrom(this.http.get<{ game: AdminGame }>(`${this.api}/admin/games/${id}`, this.options())).then((result) => result.game);
  }

  save(id: number | null, data: Omit<AdminGame, 'id' | 'status' | 'soldOutAt'>): Promise<AdminGame> {
    const url = id === null ? `${this.api}/admin/games` : `${this.api}/admin/games/${id}`;
    const request = id === null
      ? this.http.post<{ game: AdminGame }>(url, data, this.options())
      : this.http.put<{ game: AdminGame }>(url, data, this.options());
    return firstValueFrom(request).then((result) => result.game);
  }

  wins(id: number): Promise<readonly GameWin[]> {
    return firstValueFrom(this.http.get<{ wins: GameWin[] }>(`${this.api}/admin/games/${id}/wins`, this.options())).then((result) => result.wins);
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) };
  }
}
