import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { API_BASE_URL } from '../api/api.config';
import { AuthService } from '../auth/auth.service';

export interface Coupon {
  id: number;
  title: string;
  subtitle: string;
  description: string;
  imageUrl: string | null;
  isRedeemed: boolean;
}

export interface CouponRedemptionItem {
  couponId: number;
  title: string;
  subtitle: string;
  imageUrl: string | null;
}

export interface CouponRedemption {
  id: string;
  items: CouponRedemptionItem[];
  summary: string;
  validUntil: string;
}

@Injectable({ providedIn: 'root' })
export class CouponService {
  private readonly http = inject(HttpClient);
  private readonly api = inject(API_BASE_URL);
  private readonly auth = inject(AuthService);

  async list(): Promise<readonly Coupon[]> {
    return (await firstValueFrom(this.http.get<{ coupons: Coupon[] }>(`${this.api}/coupons`, this.options()))).coupons;
  }

  async getOne(id: number): Promise<Coupon | null> {
    return (await this.list()).find((coupon) => coupon.id === id) ?? null;
  }

  async redeem(couponIds: readonly number[]): Promise<CouponRedemption> {
    return (await firstValueFrom(this.http.post<{ redemption: CouponRedemption }>(`${this.api}/coupons/redeem`, { couponIds }, this.options()))).redemption;
  }

  async active(): Promise<CouponRedemption | null> {
    return (await firstValueFrom(this.http.get<{ redemption: CouponRedemption | null }>(`${this.api}/coupons/redemptions/active`, this.options()))).redemption;
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken() ?? ''}` }) };
  }
}
