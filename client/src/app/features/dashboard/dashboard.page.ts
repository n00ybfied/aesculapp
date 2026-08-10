import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

interface PharmacyNews {
  readonly title: string;
  readonly excerpt: string;
  readonly category: string;
  readonly date: string;
}

@Component({
  selector: 'app-dashboard-page',
  imports: [RouterLink],
  templateUrl: './dashboard.page.html',
})
export class DashboardPage {
  protected readonly news: readonly PharmacyNews[] = [
    {
      category: 'Gesund durch den Sommer',
      title: 'Sonnenschutz: gut vorbereitet in den Urlaub',
      excerpt: 'Wir zeigen Ihnen, worauf es bei Hautschutz und Reiseapotheke ankommt.',
      date: 'Heute',
    },
    {
      category: 'Aktion',
      title: '20 % auf ausgewählte Pflegeprodukte',
      excerpt: 'Entdecken Sie unsere Angebote für eine sanfte tägliche Pflegeroutine.',
      date: '2. August',
    },
  ];
}
