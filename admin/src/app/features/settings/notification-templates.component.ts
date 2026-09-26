import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NotificationTemplateService, type NotificationTemplate } from '../../core/settings/notification-template.service';
import { AdminChangeHistoryComponent } from '../../shared/admin-change-history.component';

@Component({
  selector: 'app-notification-templates',
  imports: [FormsModule, AdminChangeHistoryComponent],
  templateUrl: './notification-templates.component.html',
  styleUrl: './notification-templates.component.css',
})
export class NotificationTemplatesComponent {
  private readonly service = inject(NotificationTemplateService);
  protected readonly templates = signal<NotificationTemplate[]>([]);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected readonly channel = signal<'email' | 'push'>('email');
  protected readonly selectedKey = signal('');
  protected readonly auditRefresh = signal(0);
  protected readonly selected = computed(() => this.templates().find((item) => item.key === this.selectedKey()));
  protected readonly filtered = computed(() => this.templates().filter((item) => item.channel === this.channel()));
  protected title = '';
  protected body = '';
  private readonly titleEditor = viewChild<ElementRef<HTMLInputElement>>('titleEditor');
  private readonly bodyEditor = viewChild<ElementRef<HTMLTextAreaElement>>('bodyEditor');
  private insertionTarget: 'title' | 'body' = 'body';
  protected readonly tagLabels: Record<string, string> = {
    pharmacy_name: 'Apothekenname', action_url: 'Persönlicher Link', portal_name: 'Kunden-App oder Adminportal',
    invitation_prefix: 'Erneute Einladung', invitation_instruction: 'Anweisung für den Empfänger',
    staff_name: 'Mitarbeitername', appointment_type: 'Terminart',
    appointment_datetime: 'Datum und Uhrzeit', customer_name: 'Kundenname', staff_line: 'Ansprechperson, falls sichtbar',
    actor_name: 'Name des Familienmitglieds', appointment_date: 'Termindatum', appointment_time: 'Uhrzeit',
  };

  constructor() { void this.load(); }

  protected selectChannel(channel: 'email' | 'push'): void {
    this.channel.set(channel);
    this.select(this.templates().find((item) => item.channel === channel)?.key ?? '');
  }

  protected select(key: string): void {
    this.selectedKey.set(key);
    const template = this.selected();
    this.title = template?.title ?? '';
    this.body = template?.body ?? '';
    this.error.set('');
    this.success.set('');
  }

  protected focusField(field: 'title' | 'body'): void { this.insertionTarget = field; }

  protected insertTag(tag: string): void {
    const element = this.insertionTarget === 'title' ? this.titleEditor()?.nativeElement : this.bodyEditor()?.nativeElement;
    if (!element) return;
    const value = this.insertionTarget === 'title' ? this.title : this.body;
    const insert = `{{${tag}}}`;
    const start = element.selectionStart ?? value.length;
    const end = element.selectionEnd ?? start;
    const updated = value.slice(0, start) + insert + value.slice(end);
    if (this.insertionTarget === 'title') this.title = updated;
    else this.body = updated;
    queueMicrotask(() => { element.focus(); element.setSelectionRange(start + insert.length, start + insert.length); });
  }

  protected async save(): Promise<void> {
    const template = this.selected();
    if (!template || this.saving()) return;
    this.saving.set(true);
    this.error.set('');
    this.success.set('');
    try {
      this.templates.set(await this.service.save(template.key, this.title, this.body));
      this.auditRefresh.update((value) => value + 1);
      this.success.set('Vorlage gespeichert. Neue Nachrichten verwenden ab jetzt diesen Text.');
    } catch (error: unknown) {
      this.error.set(error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
        ? error.error.message : 'Die Vorlage konnte nicht gespeichert werden.');
    } finally {
      this.saving.set(false);
    }
  }

  protected async reset(): Promise<void> {
    const template = this.selected();
    if (!template?.customized || this.saving()) return;
    this.saving.set(true);
    this.error.set('');
    try {
      this.templates.set(await this.service.reset(template.key));
      this.auditRefresh.update((value) => value + 1);
      this.select(template.key);
      this.success.set('Der bisherige Standardtext wird wieder verwendet.');
    } catch {
      this.error.set('Die Vorlage konnte nicht zurückgesetzt werden.');
    } finally {
      this.saving.set(false);
    }
  }

  private async load(): Promise<void> {
    try {
      this.templates.set(await this.service.list());
      this.selectChannel('email');
    } catch {
      this.error.set('Die Nachrichtenvorlagen konnten nicht geladen werden.');
    } finally {
      this.loading.set(false);
    }
  }
}
