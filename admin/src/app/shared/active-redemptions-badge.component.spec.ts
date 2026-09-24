import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { AdminRedemptionService } from '../core/redemptions/admin-redemption.service';
import { ActiveRedemptionsBadgeComponent } from './active-redemptions-badge.component';

describe('ActiveRedemptionsBadgeComponent', () => {
  it('shows only a positive number of active redemptions', () => {
    const count = signal<number | null>(null);
    TestBed.configureTestingModule({
      imports: [ActiveRedemptionsBadgeComponent],
      providers: [{ provide: AdminRedemptionService, useValue: { activeCount: count } }],
    });
    const fixture = TestBed.createComponent(ActiveRedemptionsBadgeComponent);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('span')).toBeNull();

    count.set(0);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('span')).toBeNull();

    count.set(3);
    fixture.detectChanges();
    const badge = fixture.nativeElement.querySelector('span') as HTMLSpanElement;
    expect(badge.textContent?.trim()).toBe('3');
    expect(badge.getAttribute('aria-label')).toBe('3 aktive Einlösungen');
  });
});
