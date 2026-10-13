import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { CustomerGame, GameSpin, GamesService } from '../../core/games/games.service';
import { GameWheelPage } from './game-wheel.page';

describe('GameWheelPage', () => {
  it('visibly turns before revealing the server-selected result', async () => {
    const game: CustomerGame = {
      id: 1,
      title: 'Test-Glücksrad',
      availableFrom: '2026-10-01T10:00:00+02:00',
      availableUntil: '2026-11-01T10:00:00+01:00',
      participationMode: 'once',
      spinLimit: 1,
      remainingSpins: 1,
      noWinEnabled: false,
      soldOutText: 'Vorbei',
      status: 'active',
      prizes: [
        { id: 1, title: 'Punkte', wheelLabel: 'Punkte', kind: 'points', points: 10, probabilityBp: 5000 },
        { id: 2, title: 'Gutschein', wheelLabel: 'Gutschein', kind: 'coupon', points: 0, probabilityBp: 5000 },
      ],
    };
    const spin: GameSpin = {
      id: 1,
      gameId: 1,
      gameTitle: game.title,
      wheelIndex: 1,
      title: 'Gutschein',
      kind: 'coupon',
      points: 0,
      createdAt: '2026-10-13T10:00:00+02:00',
      redeemedAt: null,
    };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([{ path: 'gewinnspiele/:id', component: GameWheelPage }]),
        { provide: GamesService, useValue: {
          detail: async () => ({ game, spins: [] }),
          spin: async () => ({ game: { ...game, remainingSpins: 0 }, spin }),
        } },
      ],
    });

    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/gewinnspiele/1');
    await harness.fixture.whenStable();
    const page = harness.routeNativeElement as HTMLElement;
    const wheel = page.querySelector<HTMLElement>('.wheel');
    const button = page.querySelector<HTMLButtonElement>('.spin-button');
    expect(wheel).not.toBeNull();
    expect(button).not.toBeNull();

    const frames: FrameRequestCallback[] = [];
    const requestFrame = vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      frames.push(callback);
      return frames.length;
    });
    const nextWheelFrame = (): FrameRequestCallback => {
      const index = frames.findIndex((callback) => callback.name === 'draw');
      expect(index).toBeGreaterThanOrEqual(0);
      return frames.splice(index, 1)[0];
    };
    try {
      button!.click();
      await Promise.resolve();
      await Promise.resolve();
      nextWheelFrame()(0);
      nextWheelFrame()(1000);
      expect(wheel!.style.transform).not.toBe('rotate(0deg)');
      expect(page.textContent).not.toContain('HERZLICHEN GLÜCKWUNSCH!');

      nextWheelFrame()(6500);
      await Promise.resolve();
      harness.detectChanges();
      expect(wheel!.style.transform).toBe('rotate(90deg)');
      expect(page.textContent).toContain('HERZLICHEN GLÜCKWUNSCH!');
    } finally {
      requestFrame.mockRestore();
    }
  });
});
