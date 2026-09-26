import { DatePipe } from '@angular/common';
import { Component, effect, inject, input, signal } from '@angular/core';
import { AdminAuditService, type AdminChange } from '../core/audit/admin-audit.service';

@Component({
  selector: 'app-admin-change-history',
  imports: [DatePipe],
  template: `
    <section class="audit" aria-label="Änderungshistorie">
      @if (loading()) {
        <small>Änderungshistorie wird geladen …</small>
      } @else if (failed()) {
        <small>Änderungshistorie konnte nicht geladen werden.</small>
      } @else if (changes().length) {
        <p><strong>Letzte Änderung:</strong> {{ actionLabel(changes()[0].action) }}@if (entityType() === 'all') { · {{ entityLabel(changes()[0].entityType) }} #{{ changes()[0].entityId ?? '–' }} } am {{ changes()[0].occurredAt | date:'dd.MM.yyyy, HH:mm' }} von {{ changes()[0].actorName }}</p>
        <details>
          <summary>Änderungshistorie anzeigen</summary>
          <ol>
            @for (change of changes(); track change.id) {
              <li>{{ change.occurredAt | date:'dd.MM.yyyy, HH:mm' }} · {{ actionLabel(change.action) }}@if (entityType() === 'all') { · {{ entityLabel(change.entityType) }} #{{ change.entityId ?? '–' }} } von {{ change.actorName }}</li>
            }
          </ol>
          @if (hasMore()) { <button type="button" [disabled]="loadingMore()" (click)="loadMore()">{{ loadingMore() ? 'Lädt …' : 'Ältere Änderungen laden' }}</button> }
        </details>
      } @else {
        <small>Für diesen Eintrag ist noch keine Änderung dokumentiert.</small>
      }
    </section>
  `,
  styles: [`
    .audit { margin: 1rem 0; padding: .75rem 1rem; background: var(--admin-background, #f4f8fb); border: 1px solid var(--admin-border); border-radius: .65rem; color: var(--admin-text, inherit); font-size: .875rem; }
    p { margin: 0 0 .4rem; } details { margin-top: .4rem; } summary { cursor: pointer; color: var(--admin-primary-strong); } ol { margin: .6rem 0 0; padding-left: 1.5rem; } li + li { margin-top: .3rem; } button { margin-top: .6rem; border: 0; padding: .4rem 0; background: none; color: var(--admin-primary-strong); cursor: pointer; }
  `],
})
export class AdminChangeHistoryComponent {
  readonly entityType = input.required<string>();
  readonly entityId = input<number | string | null>(null);
  readonly requestPath = input<string>('');
  readonly refreshKey = input<number>(0);
  protected readonly changes = signal<readonly AdminChange[]>([]);
  protected readonly loading = signal(true);
  protected readonly failed = signal(false);
  protected readonly hasMore = signal(false);
  protected readonly loadingMore = signal(false);
  private readonly audit = inject(AdminAuditService);
  private revision = 0;

  constructor() {
    effect(() => {
      const type = this.entityType();
      const id = this.entityId();
      const path = this.requestPath();
      this.refreshKey();
      const revision = ++this.revision;
      this.loading.set(true);
      this.failed.set(false);
      void this.audit.list(type, id, path).then((page) => {
        if (revision === this.revision) {
          this.changes.set(page.changes);
          this.hasMore.set(page.hasMore);
        }
      }).catch(() => {
        if (revision === this.revision) this.failed.set(true);
      }).finally(() => {
        if (revision === this.revision) this.loading.set(false);
      });
    });
  }

  protected async loadMore(): Promise<void> {
    const before = this.changes().at(-1)?.id;
    if (!before || this.loadingMore()) return;
    const revision = this.revision;
    this.loadingMore.set(true);
    try {
      const page = await this.audit.list(this.entityType(), this.entityId(), this.requestPath(), before);
      if (revision === this.revision) {
        this.changes.update((current) => [...current, ...page.changes]);
        this.hasMore.set(page.hasMore);
      }
    } catch {
      if (revision === this.revision) this.failed.set(true);
    } finally {
      this.loadingMore.set(false);
    }
  }

  protected actionLabel(action: AdminChange['action']): string {
    return { created: 'Erstellt', updated: 'Bearbeitet', deleted: 'Gelöscht' }[action];
  }

  protected entityLabel(type: string): string {
    const labels: Record<string, string> = {
      Tenant: 'Einstellungen', NotificationTemplate: 'Nachrichtenvorlage', NewsPost: 'Beitrag',
      NewsCategory: 'Nachrichtenkategorie', Reward: 'Prämie', Coupon: 'Gutschein',
      Appointment: 'Termin', AppointmentType: 'Terminart', AppointmentResource: 'Person',
      AppointmentAvailability: 'Verfügbarkeit', AppointmentBlock: 'Sperrzeit',
      DashboardSlide: 'Sliderbild', MediaAsset: 'Medium', ChatMessage: 'Chatnachricht',
      ChatConversation: 'Chat', StaffInvitation: 'Einladung', TenantMembership: 'Mitarbeiterzugang',
      User: 'Benutzerkonto', PointTransaction: 'Punktebuchung', ActiveRedemption: 'Einlösung',
      CouponRedemption: 'Gutscheineinlösung',
    };
    return labels[type] ?? type;
  }
}
