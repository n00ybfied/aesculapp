import { signal, type Signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { FamilyService } from '../../core/family/family.service';
import { MedicationService, type MedicationDraft } from '../../core/medications/medication.service';
import { StatusMessageService } from '../../core/feedback/status-message.service';
import { ConfirmDialogService } from '../../shared/feedback/confirm-dialog.service';
import { parseMedicationDose } from './medication-dose';
import { MedicationsPage } from './medications.page';

describe('medication dose editing', () => {
  it('accepts numbers emitted by number inputs and rejects empty or unsupported doses', () => {
    expect(parseMedicationDose(0)).toBe(0);
    expect(parseMedicationDose(0.5)).toBe(0.5);
    expect(parseMedicationDose('1.5')).toBe(1.5);
    expect(parseMedicationDose('')).toBeNull();
    expect(parseMedicationDose(null)).toBeNull();
    expect(parseMedicationDose(0.25)).toBeNull();
    expect(parseMedicationDose(100)).toBeNull();
  });

  it('saves a schedule with numeric values without calling trim on numbers', async () => {
    const create = vi.fn(async (draft: MedicationDraft) => ({
      id: 1, name: draft.name, dosage: null, schedule: draft.schedule,
      notes: null, refillDate: null, hasImage: false, updatedAt: '',
    }));
    TestBed.configureTestingModule({ providers: [
      { provide: MedicationService, useValue: { create, load: async () => ({ medications: [], access: { ownerId: 1, canManage: true } }) } },
      { provide: FamilyService, useValue: { connections: signal([]) } },
      { provide: StatusMessageService, useValue: { show: vi.fn(), error: vi.fn() } },
      { provide: ConfirmDialogService, useValue: {} },
    ] });
    const page = TestBed.runInInjectionContext(() => new MedicationsPage()) as unknown as {
      form: { name: string; morningDose: number; noonDose: number; eveningDose: number; nightDose: number };
      save(): Promise<void>;
      formError: Signal<string | null>;
    };
    page.form.name = 'Magnesium';
    page.form.morningDose = 1;
    page.form.noonDose = 0.5;
    page.form.eveningDose = 0;
    page.form.nightDose = 2;

    await page.save();

    expect(page.formError()).toBeNull();
    expect(create).toHaveBeenCalledWith(expect.objectContaining({ schedule: '1-0.5-0-2' }));
  });
});
