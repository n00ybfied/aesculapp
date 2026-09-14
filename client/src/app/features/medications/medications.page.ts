import { DatePipe } from '@angular/common';
import { Component, OnDestroy, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgIcon } from '@ng-icons/core';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { FamilyService } from '../../core/family/family.service';
import { Medication, MedicationDraft, MedicationService } from '../../core/medications/medication.service';

interface MedicationForm {
  name: string;
  dosage: string;
  morningDose: string;
  noonDose: string;
  eveningDose: string;
  nightDose: string;
  notes: string;
  refillDate: string;
  image: File | null;
}

@Component({ selector: 'app-medications-page', imports: [DatePipe, FormsModule, NgIcon], templateUrl: './medications.page.html' })
export class MedicationsPage implements OnDestroy {
  private readonly medicationsApi = inject(MedicationService);
  private readonly messages = inject(StatusMessageService);
  private readonly familyApi = inject(FamilyService);
  protected readonly medications = signal<readonly Medication[]>([]);
  protected readonly imageUrls = signal<Record<number, string>>({});
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly selectedOwnerId = signal<number | null>(null);
  protected readonly canManage = signal(true);
  protected readonly family = this.familyApi.connections;
  protected readonly hasFamilyMedicationAccess = computed(() => this.family().some((connection) => connection.status === 'accepted' && connection.canViewMedication));
  protected readonly editorOpen = signal(false);
  protected readonly editingId = signal<number | null>(null);
  protected readonly photoPreview = signal<string | null>(null);
  protected readonly form: MedicationForm = { name: '', dosage: '', morningDose: '0', noonDose: '0', eveningDose: '0', nightDose: '0', notes: '', refillDate: '', image: null };

  async ngOnInit(): Promise<void> { try { await this.familyApi.load(); } catch { /* Medication access remains available without a family list. */ } await this.reload(); }
  ngOnDestroy(): void { Object.values(this.imageUrls()).forEach(URL.revokeObjectURL); this.revokePreview(); }

  protected openCreate(): void { if (!this.canManage()) return; this.resetForm(); this.editorOpen.set(true); }
  protected openEdit(item: Medication): void {
    this.form.name = item.name;
    this.form.dosage = item.dosage ?? '';
    this.setSchedule(item.schedule);
    this.form.notes = item.notes ?? '';
    this.form.refillDate = item.refillDate ?? '';
    this.form.image = null;
    this.editingId.set(item.id);
    this.revokePreview();
    this.photoPreview.set(this.imageUrls()[item.id] ?? null);
    this.editorOpen.set(true);
  }
  protected closeEditor(): void { this.editorOpen.set(false); this.resetForm(); }
  protected selectImage(event: Event): void { const input = event.target as HTMLInputElement; const image = input.files?.[0] ?? null; input.value = ''; if (image === null) return; if (!['image/jpeg', 'image/png', 'image/webp'].includes(image.type) || image.size > 5 * 1024 * 1024) { this.messages.error('Bitte wählen Sie JPEG, PNG oder WebP bis maximal 5 MB.'); return; } this.revokePreview(); this.form.image = image; this.photoPreview.set(URL.createObjectURL(image)); }
  protected async save(): Promise<void> {
    if (this.form.name.trim().length < 2 || this.saving()) return;
    this.saving.set(true);
    try { const saved = this.editingId() === null ? await this.medicationsApi.create(this.draft()) : await this.medicationsApi.update(this.editingId()!, this.draft()); this.closeEditor(); await this.reload(); this.messages.show(`„${saved.name}“ wurde gespeichert.`, { kind: 'success' }); }
    catch { this.messages.error('Das Medikament konnte nicht gespeichert werden.'); } finally { this.saving.set(false); }
  }
  protected async remove(item: Medication): Promise<void> { if (!this.canManage() || !confirm(`„${item.name}“ wirklich entfernen?`)) return; try { await this.medicationsApi.remove(item.id); await this.reload(); this.messages.show('Das Medikament wurde entfernt.', { kind: 'success' }); } catch { this.messages.error('Das Medikament konnte nicht entfernt werden.'); } }
  protected async selectOwner(id: number | null): Promise<void> { if (this.selectedOwnerId() === id) return; this.selectedOwnerId.set(id); await this.reload(); }

  private async reload(): Promise<void> {
    this.loading.set(true);
    try { const response = await this.medicationsApi.load(this.selectedOwnerId() ?? undefined); const medications = response.medications; this.canManage.set(response.access.canManage); this.medications.set(medications); await Promise.all(medications.filter((item) => item.hasImage).map(async (item) => { try { const image = URL.createObjectURL(await this.medicationsApi.image(item.id)); this.imageUrls.update((urls) => ({ ...urls, [item.id]: image })); } catch { /* A missing private image does not block the plan. */ } })); }
    catch { this.messages.error('Der Medikamentenplan konnte nicht geladen werden.'); } finally { this.loading.set(false); }
  }
  private draft(): MedicationDraft {
    return {
      name: this.form.name,
      dosage: this.form.dosage,
      schedule: [this.form.morningDose, this.form.noonDose, this.form.eveningDose, this.form.nightDose].join('-'),
      notes: this.form.notes,
      refillDate: this.form.refillDate,
      image: this.form.image,
    };
  }

  private setSchedule(schedule: string | null): void {
    const values = schedule?.match(/^(\d+(?:[.,]\d+)?)-(\d+(?:[.,]\d+)?)-(\d+(?:[.,]\d+)?)-(\d+(?:[.,]\d+)?)$/);
    [this.form.morningDose, this.form.noonDose, this.form.eveningDose, this.form.nightDose] = values?.slice(1) ?? ['0', '0', '0', '0'];
  }

  private resetForm(): void { this.form.name = ''; this.form.dosage = ''; [this.form.morningDose, this.form.noonDose, this.form.eveningDose, this.form.nightDose] = ['0', '0', '0', '0']; this.form.notes = ''; this.form.refillDate = ''; this.form.image = null; this.editingId.set(null); this.revokePreview(); this.photoPreview.set(null); }
  private revokePreview(): void { const preview = this.photoPreview(); if (preview !== null && !Object.values(this.imageUrls()).includes(preview)) URL.revokeObjectURL(preview); }
}
