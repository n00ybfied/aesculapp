import { Injectable, NgZone, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { App as CapacitorApp } from '@capacitor/app';
import { MedicationService } from './medication.service';
import { NativeMedicationReminderAdapter } from './native-medication-reminder.adapter';
import { medicationReminderSlots } from './medication-reminder-plan';
import { ProfileService } from '../profile/profile.service';

export type MedicationReminderStatus = 'unsupported' | 'disabled' | 'no-medications' | 'permission-required' | 'scheduled' | 'approximate' | 'error';

@Injectable({ providedIn: 'root' })
export class MedicationReminderService {
  private readonly native = inject(NativeMedicationReminderAdapter);
  private readonly profiles = inject(ProfileService);
  private readonly medications = inject(MedicationService);
  private readonly router = inject(Router);
  private readonly zone = inject(NgZone);
  private userId: number | null | undefined;
  private work: Promise<void> = Promise.resolve();
  private started = false;

  readonly supported = this.native.supported;
  readonly status = signal<MedicationReminderStatus>(this.supported ? 'disabled' : 'unsupported');

  async initialize(): Promise<void> {
    if (!this.supported || this.started) return;
    this.started = true;
    try {
      await this.native.listen(() => this.zone.run(() => void this.router.navigateByUrl('/medikamente')));
      await CapacitorApp.addListener('appStateChange', ({ isActive }) => {
        if (isActive) void this.refresh();
      });
    } catch {
      this.status.set('error');
    }
  }

  setUser(userId: number | null): Promise<void> {
    if (!this.supported || this.userId === userId) return Promise.resolve();
    const previousUserId = this.userId;
    this.userId = userId;
    return this.enqueue(async () => {
      if (previousUserId !== undefined && previousUserId !== userId) await this.native.cancel();
      if (userId === null) {
        this.status.set('disabled');
      } else {
        await this.synchronize(userId);
      }
    });
  }

  refresh(): Promise<void> {
    if (!this.supported || this.userId == null) return Promise.resolve();
    const userId = this.userId;
    return this.enqueue(() => this.synchronize(userId));
  }

  async requestPermission(): Promise<void> {
    if (!this.supported || this.userId == null) return;
    try {
      if (!await this.native.requestPermission()) {
        this.status.set('permission-required');
        return;
      }
      await this.refresh();
    } catch {
      this.status.set('error');
    }
  }

  async openExactTimingSettings(): Promise<void> {
    if (!this.supported) return;
    try { await this.native.openExactTimingSettings(); } catch { this.status.set('error'); }
  }

  private enqueue(action: () => Promise<void>): Promise<void> {
    const next = this.work.then(action).catch(() => this.status.set('error'));
    this.work = next;
    return next;
  }

  private async synchronize(userId: number): Promise<void> {
    const profile = await this.profiles.load();
    if (this.userId !== userId || profile.id !== userId) return;
    if (!profile.medicationPushEnabled) {
      await this.native.cancel();
      this.status.set('disabled');
      return;
    }

    const { medications } = await this.medications.load();
    if (this.userId !== userId) return;
    const slots = medicationReminderSlots(profile, medications);
    if (slots.length === 0) {
      await this.native.cancel();
      this.status.set('no-medications');
      return;
    }
    if (!await this.native.hasPermission()) {
      await this.native.cancel();
      this.status.set('permission-required');
      return;
    }
    const timing = await this.native.replace(slots);
    this.status.set(timing === 'exact' ? 'scheduled' : 'approximate');
  }
}
