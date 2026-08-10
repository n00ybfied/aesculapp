# AGENTS.md

## Zweck

Diese Datei enthält verbindliche Arbeitsanweisungen für KI-Coding-Agenten, insbesondere Codex.

Vor Änderungen am Projekt müssen die projektspezifischen Entwicklungsregeln gelesen und berücksichtigt werden.

## Vor jeder Implementierung

1. Lies `docs/ENTWICKLUNG.md`.
2. Berücksichtige die dort dokumentierten Architektur-, UX- und Technologieentscheidungen.
3. Halte dich zusätzlich an die aktuellen offiziellen Angular-Empfehlungen für KI-gestützte Entwicklung:
   `https://angular.dev/ai/develop-with-ai`
4. Bestehende Architekturentscheidungen dürfen nicht eigenmächtig geändert werden.
5. Wenn eine Anforderung einer dokumentierten Architekturentscheidung widerspricht oder eine grundlegende neue Architekturentscheidung notwendig wäre, weise darauf hin, bevor du sie umsetzt.

## Aktueller Projektstand

Das Projekt befindet sich in der ersten Frontend-Prototyping-Phase.

Der aktuelle Schwerpunkt ist:

- schnell eine visuell überzeugende und testbare App erstellen
- grundlegende UX und Navigation erproben
- Datenstrukturen für die spätere REST API vorbereiten
- noch kein Symfony-Backend implementieren
- noch keine produktive Authentifizierung oder Persistenz implementieren

## Verbindlicher Technologie-Stack

### Frontend

- Angular
- Client Side Rendering (CSR)
- Tailwind CSS
- Progressive Web App (PWA)
- Capacitor für iOS und Android

### Backend

Für eine spätere Phase vorgesehen:

- Symfony
- REST API

Das Backend wird in der aktuellen Prototyping-Phase noch nicht implementiert.

## Angular

Verwende moderne Angular-Konventionen entsprechend der aktuellen offiziellen Angular-Dokumentation.

Insbesondere:

- Standalone Components
- striktes TypeScript
- Signals für lokalen und reaktiven State
- `input()`, `output()`, `model()` und `computed()` wo passend
- `inject()` für Dependency Injection
- natives Control Flow mit `@if`, `@for` und `@switch`
- Lazy Loading für Feature-Routen
- kleine Komponenten mit klarer Verantwortung
- bevorzugt Signal Forms; andernfalls Reactive Forms
- kein unnötiges `any`
- `unknown` bei tatsächlich unbekannten Typen
- Accessibility von Beginn an berücksichtigen

## Architekturgrenzen

Fachkomponenten dürfen nicht direkt auf folgende technische Implementierungsdetails zugreifen:

- lokale Mock-JSON-Dateien
- HTTP
- Capacitor-Plugins
- native Swift-/Kotlin-APIs

Verwende Services, Repositories beziehungsweise Adapter als Abstraktionsschicht.

Beispiel:

```text
Feature Component
        ↓
Repository / Service
        ↓
Mock Implementation

später:

Feature Component
        ↓
Repository / Service
        ↓
Symfony REST API
```

## Mock-Daten

In der aktuellen Phase werden lokale Dummy-/Mock-Daten verwendet.

Die Datenstrukturen sollen bereits möglichst realistisch der späteren Symfony-REST-API entsprechen.

Komponenten dürfen Mock-JSON-Dateien niemals direkt importieren oder laden.

Mock-Daten werden ausschließlich über dafür vorgesehene Services oder Repository-Implementierungen bereitgestellt.

Der spätere Wechsel von Mock-Daten zur REST API soll möglichst keine Änderungen an Fachkomponenten erfordern.

## Capacitor und native Funktionen

Native Gerätefunktionen werden grundsätzlich gekapselt.

Beispiel:

```text
ReceiptScannerComponent
        ↓
BarcodeScannerService
        ↓
Capacitor Plugin
        ↓
iOS / Android
```

Priorität bei nativen Funktionen:

1. offizielles Capacitor-Plugin
2. etabliertes Community-Plugin, falls keine geeignete offizielle Lösung existiert
3. eigenes Capacitor-Plugin beziehungsweise eigener Swift-/Kotlin-Code nur als letzte Option

Eigener nativer Code soll nicht erstellt werden, wenn eine geeignete offizielle Capacitor-Lösung vorhanden ist.

Für Browserentwicklung und Prototyping sollen native Services bei Bedarf durch Mock-Implementierungen ersetzt werden können.

## Styling und Themes

Tailwind CSS ist das primäre Styling-Werkzeug.

Das Styling muss von Beginn an theme-fähig bleiben.

Verwende semantische Design Tokens beziehungsweise CSS Custom Properties für wiederkehrende visuelle Rollen, insbesondere:

- background
- surface
- foreground/text
- muted
- primary
- accent
- border
- success
- warning
- danger

Vermeide hart codierte Markenfarben in Fachkomponenten.

Nicht bevorzugt:

```html
<button class="bg-emerald-600 text-white">
```

Bevorzugt sind semantische Theme-Werte.

Das erste Theme ist:

- Light Mode
- hell
- leicht
- ruhig
- vertrauenswürdig
- gesundheitsorientiert
- mit wenigen Farben
- gut lesbar
- mobile-first

Die Architektur muss spätere Erweiterungen ermöglichen:

- Dark Mode
- kundenspezifische Themes
- White-Label-Apps
- SaaS-Mandantenbranding

## Mandantenfähigkeit

Aktuell erhält jede Apotheke eine eigene App.

Es gibt daher derzeit:

- keine Apothekenauswahl
- keine Mandantenumschaltung
- keine Auswahl einer Stammapotheke

Trotzdem muss die Anwendung technisch auf ein späteres Mandantensystem vorbereitet sein.

Die aktuelle Apotheke ergibt sich zunächst aus der App-/Build-Konfiguration.

Mandantenkennungen sind niemals Sicherheitsmerkmale. Die spätere Symfony-API muss Berechtigungen und Mandantenzugehörigkeiten serverseitig prüfen.

## App-Zugriff

Die eigentliche App kann ausschließlich nach Anmeldung verwendet werden.

Öffentlich zugänglich sind nur notwendige Authentifizierungs- und Rechtsseiten, insbesondere:

- Login
- Registrierung
- Passwort vergessen
- Passwort zurücksetzen
- E-Mail-Verifizierung
- Impressum
- Datenschutz
- gegebenenfalls Nutzungsbedingungen

Der eigentliche App-Bereich liegt hinter Authentifizierung.

Im aktuellen Prototyp wird Authentifizierung nur simuliert.

## Erste Features des Prototyps

Zunächst werden umgesetzt:

- Login
- Registrierung und zugehörige Auth-Screens
- Profil
- Dashboard mit neuesten Informationen
- Punkte und Prämien
- Rechnungsscanner / QR-Scanner

Der Rechnungsscanner verwendet im Prototyp zunächst eine Mock-Implementierung.

Für die native Umsetzung soll bevorzugt das offizielle Capacitor Barcode Scanner Plugin verwendet werden.

## App Shell

### Header

Die App besitzt eine schlanke Headerbar mit:

- Hamburger-Menü
- kleinem App-/Apothekenlogo
- kleinem Profilbild
- Profilbild führt zur Profilseite

### Bottom Navigation

Die App besitzt eine mobile Footerbar beziehungsweise Bottom Navigation.

Die endgültigen Einträge sind noch nicht festgelegt.

Der QR-/Rechnungsscanner ist als wichtige, gegebenenfalls zentral hervorgehobene Aktion vorgesehen.

Die iOS-/Android-Safe-Areas müssen berücksichtigt werden.

## Loading-Verhalten

Bei Seitenwechseln beziehungsweise initialem Laden von Seitendaten soll der Benutzer keine teilweise geladenen Inhalte oder Layoutsprünge sehen.

Während des Ladens:

- Header bleibt sichtbar
- Bottom Navigation bleibt sichtbar
- der Inhaltsbereich wird vollständig durch einen Loading-Zustand ersetzt
- zentraler Loading Spinner
- Hintergrund verwendet den semantischen Theme-Background

Die App Shell bleibt dadurch stabil.

## UX-Grundsätze

Die Anwendung ist primär für Smartphones konzipiert.

Sie soll wie eine eigenständige Gesundheits-App wirken und nicht wie eine klassische Website in einem App-Rahmen.

Achte insbesondere auf:

- einfache Navigation
- geringe kognitive Belastung
- klare visuelle Hierarchie
- großzügige Abstände
- ausreichend große Touch-Flächen
- gute Lesbarkeit
- hohe Kontraste
- verständliche Icons
- konsistente Komponenten
- Accessibility

## Arbeitsweise

Implementiere nicht vorsorglich Funktionen, die aktuell nicht benötigt werden.

Bevorzuge:

- einfache Lösungen
- klare Abstraktionen
- kleine, nachvollziehbare Änderungen
- wiederverwendbare UI-Komponenten
- gut typisierte Datenmodelle
- testbare Services
- wenig technische Sonderlösungen

Vermeide:

- Overengineering
- unnötige Dependencies
- eigene Implementierungen vorhandener Capacitor-Funktionalität
- frühzeitige Backend-Implementierung
- fest verdrahtete Mandantenlogik
- fest verdrahtete Theme-Werte
- direkte technische Abhängigkeiten in Fachkomponenten

## Dokumentation

Wenn eine neue dauerhafte Architekturentscheidung getroffen wird, soll sie in `docs/ENTWICKLUNG.md` dokumentiert werden.

Diese Datei ist eine Arbeitsanweisung für Codex und ersetzt nicht die ausführlichere Projektdokumentation.
