import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { AppointmentTabsPage } from './appointment-tabs.page';

@Component({ template: '<p>Terminbuchung</p>' })
class BookingView {}

@Component({ template: '<p>Bevorstehende Termine</p>' })
class MyAppointmentsView {}

describe('AppointmentTabsPage', () => {
  it('opens direct links on the correct tab and switches between both views', async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([{
        path: 'termine',
        component: AppointmentTabsPage,
        children: [
          { path: '', component: BookingView },
          { path: 'meine', component: MyAppointmentsView },
        ],
      }])],
    });

    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/termine/meine');
    let page = harness.routeNativeElement as HTMLElement;
    expect(page.textContent).toContain('Bevorstehende Termine');
    expect(page.querySelector<HTMLAnchorElement>('a[href="/termine/meine"]')?.getAttribute('aria-current')).toBe('page');

    await harness.navigateByUrl('/termine');
    page = harness.routeNativeElement as HTMLElement;
    expect(page.textContent).toContain('Terminbuchung');
    expect(page.textContent).not.toContain('Bevorstehende Termine');
    expect(page.querySelector<HTMLAnchorElement>('a[href="/termine"]')?.getAttribute('aria-current')).toBe('page');
  });
});
