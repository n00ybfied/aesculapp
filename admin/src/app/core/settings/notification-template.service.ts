import { HttpClient, HttpHeaders } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AdminAuthService } from '../auth/admin-auth.service';

export interface NotificationTemplate {
  readonly key: string;
  readonly channel: 'email' | 'push';
  readonly label: string;
  readonly title: string;
  readonly body: string;
  readonly customized: boolean;
  readonly tags: readonly string[];
  readonly required: readonly string[];
}

@Injectable({ providedIn: 'root' })
export class NotificationTemplateService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AdminAuthService);
  private readonly url = (['localhost', '127.0.0.1'].includes(location.hostname)
    ? 'http://localhost:6080/api/v1'
    : 'https://api.aesculapp.floatbox.at/api/v1') + '/admin/settings/notification-templates';

  list(): Promise<NotificationTemplate[]> {
    return firstValueFrom(this.http.get<{ templates: NotificationTemplate[] }>(this.url, { headers: this.headers() }))
      .then((response) => response.templates);
  }

  save(key: string, title: string, body: string): Promise<NotificationTemplate[]> {
    return firstValueFrom(this.http.put<{ templates: NotificationTemplate[] }>(`${this.url}/${encodeURIComponent(key)}`, { title, body }, { headers: this.headers() }))
      .then((response) => response.templates);
  }

  reset(key: string): Promise<NotificationTemplate[]> {
    return firstValueFrom(this.http.delete<{ templates: NotificationTemplate[] }>(`${this.url}/${encodeURIComponent(key)}`, { headers: this.headers() }))
      .then((response) => response.templates);
  }

  private headers(): HttpHeaders {
    return new HttpHeaders({ Authorization: `Bearer ${this.auth.accessToken()}` });
  }
}
