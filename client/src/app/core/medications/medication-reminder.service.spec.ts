import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { MedicationService } from './medication.service';
import { MedicationReminderService } from './medication-reminder.service';
import { NativeMedicationReminderAdapter } from './native-medication-reminder.adapter';
import { ProfileService } from '../profile/profile.service';

describe('MedicationReminderService', () => {
  const profile = {
    id: 4,
    medicationPushEnabled: true,
    morningReminderTime: '08:00',
    noonReminderTime: '12:00',
    eveningReminderTime: '18:00',
    nightReminderTime: '22:00',
  };
  const native = {
    supported: true,
    cancel: vi.fn().mockResolvedValue(undefined),
    hasPermission: vi.fn().mockResolvedValue(true),
    replace: vi.fn().mockResolvedValue('exact'),
  };
  const profiles = { load: vi.fn().mockResolvedValue(profile) };
  const medications = { load: vi.fn().mockResolvedValue({ medications: [{ schedule: '1-0-0-1' }] }) };
  let service: MedicationReminderService;

  beforeEach(() => {
    vi.clearAllMocks();
    profiles.load.mockResolvedValue(profile);
    medications.load.mockResolvedValue({ medications: [{ schedule: '1-0-0-1' }] });
    native.hasPermission.mockResolvedValue(true);
    native.replace.mockResolvedValue('exact');
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: NativeMedicationReminderAdapter, useValue: native },
        { provide: ProfileService, useValue: profiles },
        { provide: MedicationService, useValue: medications },
      ],
    });
    service = TestBed.inject(MedicationReminderService);
  });

  it('schedules the signed-in customer’s own active intake slots', async () => {
    await service.setUser(4);
    expect(native.cancel).not.toHaveBeenCalled();
    expect(native.replace).toHaveBeenCalledWith([
      { id: 830001, hour: 8, minute: 0 },
      { id: 830004, hour: 22, minute: 0 },
    ]);
    expect(service.status()).toBe('scheduled');
  });

  it('cancels all medication reminders when the customer signs out', async () => {
    await service.setUser(4);
    native.cancel.mockClear();
    await service.setUser(null);
    expect(native.cancel).toHaveBeenCalledOnce();
    expect(service.status()).toBe('disabled');
  });

  it('does not discard device reminders before the initial session restore completes', async () => {
    await service.setUser(null);
    expect(native.cancel).not.toHaveBeenCalled();
    await service.setUser(4);
    expect(native.cancel).toHaveBeenCalledOnce();
    expect(native.replace).toHaveBeenCalledOnce();
  });

  it('does not schedule without device notification permission', async () => {
    native.hasPermission.mockResolvedValue(false);
    await service.setUser(4);
    expect(native.replace).not.toHaveBeenCalled();
    expect(service.status()).toBe('permission-required');
  });

  it('keeps existing reminders when loading the server plan fails temporarily', async () => {
    await service.setUser(4);
    native.cancel.mockClear();
    profiles.load.mockRejectedValueOnce(new Error('offline'));
    await service.refresh();
    expect(native.cancel).not.toHaveBeenCalled();
    expect(service.status()).toBe('error');
  });
});
