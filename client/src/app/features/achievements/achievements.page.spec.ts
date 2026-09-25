import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AchievementService } from '../../core/achievements/achievement.service';
import { provideLucideIcons } from '../../core/icons/lucide-icons';
import { AchievementsPage } from './achievements.page';

describe('AchievementsPage', () => {
  it('shows missing profile details and the configured points', async () => {
    TestBed.configureTestingModule({
      imports: [AchievementsPage],
      providers: [
        provideRouter([]),
        provideLucideIcons(),
        { provide: AchievementService, useValue: { list: async () => [{
          id: 'complete_profile', title: 'Profil vervollständigen', description: 'Angaben ergänzen',
          points: 55, completed: false, progress: 3, target: 8,
          missingFields: ['Telefon', 'Geburtsdatum'], actionPath: '/profil',
        }] } },
      ],
    });

    const fixture = TestBed.createComponent(AchievementsPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.textContent).toContain('55 Punkte');
    expect(root.textContent).toContain('Noch offen: Telefon, Geburtsdatum');
    expect(root.querySelector('a[href="/profil"]')).not.toBeNull();
  });

  it('strikes through completed achievements in green', async () => {
    TestBed.configureTestingModule({
      imports: [AchievementsPage],
      providers: [
        provideRouter([]),
        provideLucideIcons(),
        { provide: AchievementService, useValue: { list: async () => [{
          id: 'complete_profile', title: 'Profil vervollständigen', description: 'Angaben ergänzen',
          points: 55, completed: true, progress: 8, target: 8, missingFields: [], actionPath: '/profil',
        }] } },
      ],
    });

    const fixture = TestBed.createComponent(AchievementsPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.querySelector('h2')?.classList.contains('line-through')).toBe(true);
    expect(root.querySelector('h2')?.classList.contains('text-success')).toBe(true);
    expect(root.textContent).toContain('Erledigt');
  });
});
