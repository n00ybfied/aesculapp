import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ThemeService } from '../../core/theme/theme.service';
import { WebsitePage } from './website.page';

@Component({
  imports: [WebsitePage],
  template: `
    @if (mounted()) {
      <app-website-page [style.display]="active() ? 'block' : 'none'" />
    }
  `,
})
class WebsiteHost {
  readonly mounted = signal(false);
  readonly active = signal(false);
}

describe('Embedded website session', () => {
  it('keeps the same iframe when another app page is shown and the user returns', async () => {
    TestBed.configureTestingModule({
      imports: [WebsiteHost],
      providers: [{ provide: ThemeService, useValue: { activeTheme: { websiteUrl: 'https://example.test/' } } }],
    });
    const fixture = TestBed.createComponent(WebsiteHost);
    fixture.componentInstance.mounted.set(true);
    fixture.componentInstance.active.set(true);
    await fixture.whenStable();
    const iframe = (fixture.nativeElement as HTMLElement).querySelector('iframe');
    expect(iframe).not.toBeNull();

    fixture.componentInstance.active.set(false);
    await fixture.whenStable();
    expect((fixture.nativeElement as HTMLElement).querySelector('iframe')).toBe(iframe);

    fixture.componentInstance.active.set(true);
    await fixture.whenStable();
    expect((fixture.nativeElement as HTMLElement).querySelector('iframe')).toBe(iframe);
  });
});
