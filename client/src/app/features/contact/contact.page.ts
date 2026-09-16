import { Component, ElementRef, OnDestroy, effect, inject, signal, viewChild } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideClock3, lucideMail, lucideMapPin, lucidePhone } from '@ng-icons/lucide';
import type { Map } from 'leaflet';
import { PharmacyContactService, type ContactDay, type PharmacyContact } from '../../core/contact/pharmacy-contact.service';

@Component({
  selector: 'app-contact-page',
  imports: [NgIcon],
  providers: [provideIcons({ lucideClock3, lucideMail, lucideMapPin, lucidePhone })],
  templateUrl: './contact.page.html',
  styleUrl: './contact.page.css',
})
export class ContactPage implements OnDestroy {
  private readonly contacts = inject(PharmacyContactService);
  private readonly mapHost = viewChild<ElementRef<HTMLElement>>('mapHost');
  private map: Map | null = null;
  protected readonly contact = signal<PharmacyContact | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly hasError = signal(false);
  protected readonly days: ReadonlyArray<{ readonly key: ContactDay; readonly label: string }> = [{ key: 'monday', label: 'Montag' }, { key: 'tuesday', label: 'Dienstag' }, { key: 'wednesday', label: 'Mittwoch' }, { key: 'thursday', label: 'Donnerstag' }, { key: 'friday', label: 'Freitag' }, { key: 'saturday', label: 'Samstag' }, { key: 'sunday', label: 'Sonntag' }];

  constructor() {
    effect(() => {
      const contact = this.contact();
      const host = this.mapHost();
      if (contact && host && this.hasMap(contact)) void this.renderMap(contact, host.nativeElement);
    });
    void this.load();
  }
  ngOnDestroy(): void { this.map?.remove(); }
  protected hasMap(contact: PharmacyContact): boolean { return contact.latitude !== null && contact.longitude !== null; }
  protected hasOpeningHours(contact: PharmacyContact): boolean { return this.days.some((day) => contact.openingHours[day.key] !== ''); }

  private async load(): Promise<void> { try { this.contact.set(await this.contacts.get()); } catch { this.hasError.set(true); } finally { this.isLoading.set(false); } }
  private async renderMap(contact: PharmacyContact, host: HTMLElement): Promise<void> {
    if (this.map) return;
    const leaflet = await import('leaflet');
    if (this.map) return;
    this.map = leaflet.map(host, { scrollWheelZoom: false, zoomControl: true }).setView([contact.latitude!, contact.longitude!], contact.mapZoom);
    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende' }).addTo(this.map);
    leaflet.marker([contact.latitude!, contact.longitude!], { icon: leaflet.divIcon({ className: 'contact-map-marker', html: '<span>⌖</span>', iconSize: [38, 38], iconAnchor: [19, 19] }) }).addTo(this.map);
  }
}
