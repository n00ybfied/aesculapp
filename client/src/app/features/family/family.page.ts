import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { FamilyConnection, FamilyService } from '../../core/family/family.service';

@Component({ selector: 'app-family-page', imports: [FormsModule, NgIcon], templateUrl: './family.page.html' })
export class FamilyPage implements OnInit {
  private readonly familyApi = inject(FamilyService);
  private readonly route = inject(ActivatedRoute);
  private readonly messages = inject(StatusMessageService);
  protected readonly connections = this.familyApi.connections;
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected email = '';

  async ngOnInit(): Promise<void> {
    try { await this.familyApi.load(); const token = this.route.snapshot.queryParamMap.get('token'); if (token !== null) { await this.familyApi.accept(token); this.messages.show('Die Familienverbindung wurde bestätigt. Legen Sie nun Ihre Freigaben fest.', { kind: 'success' }); } }
    catch { this.messages.error('Die Familienzugänge konnten nicht geladen werden.'); } finally { this.loading.set(false); }
  }
  protected async invite(): Promise<void> { if (!this.email.trim() || this.saving()) return; this.saving.set(true); try { await this.familyApi.invite(this.email.trim()); this.email = ''; this.messages.show('Die Einladung wurde versendet, sofern ein passendes Kundenkonto besteht.', { kind: 'success' }); } catch { this.messages.error('Die Einladung konnte nicht versendet werden.'); } finally { this.saving.set(false); } }
  protected async acceptInvitation(connection: FamilyConnection): Promise<void> { if (this.saving()) return; this.saving.set(true); try { await this.familyApi.acceptListedInvitation(connection.id); this.messages.show('Die Familienverbindung wurde bestätigt. Legen Sie nun Ihre Freigaben fest.', { kind: 'success' }); } catch { this.messages.error('Die Einladung konnte nicht angenommen werden.'); } finally { this.saving.set(false); } }
  protected async requestPointSharing(connection: FamilyConnection): Promise<void> { if (this.saving()) return; this.saving.set(true); try { await this.familyApi.requestPointSharing(connection.id); this.messages.show('Die Anfrage zur Punkteteilung wurde versendet.', { kind: 'success' }); } catch { this.messages.error('Die Punkteteilung konnte nicht angefragt werden.'); } finally { this.saving.set(false); } }
  protected async acceptPointSharing(connection: FamilyConnection): Promise<void> { if (this.saving()) return; this.saving.set(true); try { await this.familyApi.acceptPointSharing(connection.id); this.messages.show('Der Punkteteilung wurde zugestimmt.', { kind: 'success' }); } catch { this.messages.error('Die Punkteteilung konnte nicht angenommen werden.'); } finally { this.saving.set(false); } }
  protected async saveAccess(connection: FamilyConnection, view: boolean, manage: boolean): Promise<void> { this.saving.set(true); try { await this.familyApi.updateMedicationAccess(connection.id, view, manage); this.messages.show('Ihre Medikamentenfreigabe wurde gespeichert.', { kind: 'success' }); } catch { this.messages.error('Die Freigabe konnte nicht gespeichert werden.'); } finally { this.saving.set(false); } }
  protected async disconnect(connection: FamilyConnection): Promise<void> { if (!confirm(`Verbindung mit „${connection.other.displayName}“ wirklich trennen?`)) return; try { await this.familyApi.disconnect(connection.id); this.messages.show('Die Familienverbindung wurde getrennt.', { kind: 'success' }); } catch { this.messages.error('Die Verbindung konnte nicht getrennt werden.'); } }
}
