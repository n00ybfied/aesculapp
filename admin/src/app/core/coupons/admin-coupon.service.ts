import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { AdminAuthService } from '../auth/admin-auth.service';

export interface AdminCoupon {
  id: number;
  title: string;
  subtitle: string;
  description: string;
  imageUrl: string | null;
  isVisible: boolean;
  availableFrom: string | null;
  availableUntil: string | null;
}

export interface AdminCouponPage {
  readonly coupons: readonly AdminCoupon[];
  readonly page: number;
  readonly total: number;
  readonly totalPages: number;
}

@Injectable({ providedIn: 'root' })
export class AdminCouponService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly api = ['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1'
    : 'https://api.aesculapp.floatbox.at/api/v1';

  async list(page = 1, pageSize: 10 | 25 | 50 | 'all' = 25, query = ''): Promise<AdminCouponPage> {
    return firstValueFrom(this.http.get<AdminCouponPage>(`${this.api}/admin/coupons`, {
      ...this.options(),
      params: { page: String(page), pageSize: String(pageSize), query },
    }));
  }

  async get(id: number): Promise<AdminCoupon> {
    const response = await firstValueFrom(
      this.http.get<{ coupon: AdminCoupon }>(`${this.api}/admin/coupons/${id}`, this.options()),
    );

    return response.coupon;
  }

  save(id: number | null, data: FormData): Promise<unknown> {
    const url = id === null ? `${this.api}/admin/coupons` : `${this.api}/admin/coupons/${id}/update`;
    return firstValueFrom(this.http.post(url, data, this.options()));
  }

  toggle(item: AdminCoupon): Promise<unknown> {
    return firstValueFrom(
      this.http.patch(
        `${this.api}/admin/coupons/${item.id}/visibility`,
        { isVisible: !item.isVisible },
        this.options(),
      ),
    );
  }

  delete(id: number): Promise<void> {
    return firstValueFrom(this.http.delete<void>(`${this.api}/admin/coupons/${id}`, this.options()));
  }

  private options(): { headers: HttpHeaders } {
    return { headers: new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` }) };
  }
}
