import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideLucideIcons } from '../../core/icons/lucide-icons';
import { type Achievement } from '../../core/achievements/achievement.service';
import { DashboardAchievementStripComponent } from './dashboard-achievement-strip.component';

const openTask: Achievement = {
  id: 'complete_profile', title: 'Profil vervollständigen', description: '', points: 55,
  completed: false, progress: 5, target: 8, missingFields: [], actionPath: '/profil',
};

describe('DashboardAchievementStripComponent', () => {
  beforeEach(() => TestBed.configureTestingModule({
    imports: [DashboardAchievementStripComponent],
    providers: [provideRouter([]), provideLucideIcons()],
  }));

  it('links the next open task to its action and omits the all-trophies link for one task', () => {
    const fixture = TestBed.createComponent(DashboardAchievementStripComponent);
    fixture.componentRef.setInput('achievements', [openTask]);
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.textContent).toContain('Profil vervollständigen');
    expect(root.textContent).toContain('5 von 8 geschafft');
    expect(root.textContent).toContain('+55 Punkte');
    expect(root.querySelector<HTMLAnchorElement>('a[href="/profil"]')).not.toBeNull();
    expect(root.textContent).not.toContain('Alle Trophäen');
  });

  it('shows the first unfinished task and the all-trophies link when more exist', () => {
    const fixture = TestBed.createComponent(DashboardAchievementStripComponent);
    fixture.componentRef.setInput('achievements', [
      { ...openTask, id: 'done', title: 'Bereits erledigt', completed: true },
      openTask,
    ]);
    fixture.detectChanges();
    const root = fixture.nativeElement as HTMLElement;
    expect(root.textContent).toContain('Profil vervollständigen');
    expect(root.textContent).not.toContain('Bereits erledigt');
    expect(root.querySelector<HTMLAnchorElement>('a[href="/trophaeen"]')?.textContent).toContain('Alle Trophäen');
  });

  it('hides the task bar after completion', () => {
    const fixture = TestBed.createComponent(DashboardAchievementStripComponent);
    fixture.componentRef.setInput('achievements', [{ ...openTask, completed: true }]);
    fixture.detectChanges();
    expect((fixture.nativeElement as HTMLElement).textContent?.trim()).toBe('');
  });
});
