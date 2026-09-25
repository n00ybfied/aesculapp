import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideLucideIcons } from '../../core/icons/lucide-icons';
import { AchievementCelebrationService } from '../../core/achievements/achievement-celebration.service';
import { AchievementCelebrationComponent } from './achievement-celebration.component';

describe('AchievementCelebrationComponent', () => {
  it('shows the unlocked trophy and awarded points, then stays closed after dismissal', async () => {
    TestBed.configureTestingModule({
      imports: [AchievementCelebrationComponent],
      providers: [provideRouter([]), provideLucideIcons()],
    });
    const fixture = TestBed.createComponent(AchievementCelebrationComponent);
    const celebrations = TestBed.inject(AchievementCelebrationService);
    fixture.detectChanges();
    expect((fixture.nativeElement as HTMLElement).querySelector('[role="dialog"]')).toBeNull();

    celebrations.celebrate({ title: 'Profil vervollständigen', points: 55 });
    fixture.detectChanges();
    await fixture.whenStable();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.querySelector('[role="dialog"]')).not.toBeNull();
    expect(root.textContent).toContain('Profil vervollständigen');
    expect(root.textContent).toContain('+55');
    expect(root.querySelector<HTMLAnchorElement>('a[href="/trophaeen"]')).not.toBeNull();

    root.querySelector<HTMLButtonElement>('.continue-button')?.click();
    fixture.detectChanges();
    expect(celebrations.current()).toBeNull();
    expect(root.querySelector('[role="dialog"]')).toBeNull();
    fixture.detectChanges();
    expect(root.querySelector('[role="dialog"]')).toBeNull();
  });

  it('shows zero points without claiming a credit when no bonus is configured', () => {
    TestBed.configureTestingModule({
      imports: [AchievementCelebrationComponent],
      providers: [provideRouter([]), provideLucideIcons()],
    });
    const fixture = TestBed.createComponent(AchievementCelebrationComponent);
    TestBed.inject(AchievementCelebrationService).celebrate({ title: 'Profil vervollständigen', points: 0 });
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.textContent).toContain('+0');
    expect(root.textContent).toContain('derzeit keine Punkte');
    expect(root.textContent).not.toContain('gutgeschrieben');
  });
});
