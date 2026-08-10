# Entwicklungsgrundlage

## Technischer Stack

Für das Projekt werden folgende grundlegende Technologieentscheidungen festgelegt:

### Frontend
- Angular
- Client Side Rendering (CSR)
- Tailwind CSS für Layout, Styling und responsive Oberflächen

### Backend
- Symfony
- REST API als Schnittstelle zwischen Frontend und Backend

### Bereitstellung
- Progressive Web App (PWA)
- Native Apps für iOS und Android auf Basis derselben Angular-Codebasis
- Capacitor als native Laufzeit und Bridge zu gerätespezifischen Funktionen

## Grundprinzip

Das Angular-Frontend ist vom Symfony-Backend getrennt und kommuniziert ausschließlich über die REST API.

Die Anwendung wird primär als clientseitig gerenderte Webanwendung entwickelt. Dieselbe Angular-Codebasis dient sowohl als PWA als auch als Grundlage für die nativen iOS- und Android-Apps über Capacitor.


## Entwicklungsphase 1: Frontend-Prototyp

Die Entwicklung beginnt mit einem reinen Angular-Frontend-Prototypen. Das Symfony-Backend wird in dieser Phase noch nicht umgesetzt.

Ziel ist es, möglichst früh eine visuell und funktional greifbare Version der Anwendung präsentieren und gemeinsam mit der Pilotapotheke testen zu können.

### Mock-Daten

Die benötigten Daten werden zunächst über lokale JSON-Dateien bereitgestellt.

Diese JSON-Strukturen sollen bereits möglichst jener Struktur entsprechen, die später von der Symfony REST API geliefert wird. Dadurch kann das Frontend später mit möglichst wenig Umbau von lokalen Mock-Daten auf echte API-Endpunkte umgestellt werden.

Beispielhafte Struktur:

```text
src/
└── assets/
    └── mock/
        ├── auth.json
        ├── profile.json
        ├── dashboard.json
        ├── rewards.json
        └── receipts.json
```

Der Zugriff auf Mock-Daten erfolgt nicht direkt aus den Komponenten. Stattdessen werden Angular Services verwendet, die später gegen echte API-Services ausgetauscht beziehungsweise umgestellt werden können.

```text
Komponente
    ↓
Angular Service
    ↓
Mock JSON

später:

Komponente
    ↓
Angular Service
    ↓
Symfony REST API
```

### Erste Prototyp-Funktionen

Für den ersten grafischen Prototypen werden folgende Bereiche umgesetzt:

#### Login
- Login-Maske
- simulierte Anmeldung
- Weiterleitung in den geschützten App-Bereich
- noch keine echte Authentifizierung

#### Profil
- persönliche Basisdaten
- Kontaktdaten
- Benachrichtigungseinstellungen
- grundlegende Kontoeinstellungen

#### Dashboard
- Begrüßung
- aktueller Punktestand
- neueste Informationen der Apotheke
- aktuelle Aktionen
- relevante Hinweise
- Schnellzugriffe auf zentrale Funktionen

#### Punkte und Prämien
- aktueller Punktestand
- Punktehistorie
- verfügbare Prämien und Gutscheine
- benötigte Punkte je Prämie
- grafische Darstellung des Fortschritts
- Einlösung im Prototyp nur simuliert

#### Rechnungsscanner
- Oberfläche zum Scannen beziehungsweise Hochladen eines Belegs
- Simulation eines erkannten QR-Codes
- Anzeige der erkannten Einkaufsdaten
- Berechnung beziehungsweise Anzeige der erhaltenen Punkte
- Bestätigung des erfolgreichen Imports
- noch keine echte Anbindung an Kassensystem oder QR-Code-Auswertung

### Ziel der ersten Phase

Der Prototyp soll insbesondere dazu dienen:

- das grundlegende Look & Feel der App festzulegen,
- Navigation und Informationsarchitektur zu testen,
- zentrale Kundenabläufe früh sichtbar zu machen,
- Feedback der Pilotapotheke einzuholen,
- die späteren API-Datenstrukturen vorzubereiten,
- technische Backend-Entwicklung erst nach Klärung der wichtigsten Oberflächen und Abläufe zu beginnen.

Der Prototyp ist ausdrücklich noch keine produktive Anwendung. Persistenz, echte Authentifizierung, serverseitige Validierung, Rechteprüfung und integrationsabhängige Funktionen folgen in späteren Entwicklungsphasen.


## UX- und Theme-Grundlagen

Die Anwendung wird zunächst mit einem leichten, hellen Erscheinungsbild für den Gesundheits- und Apothekenbereich gestaltet.

Das visuelle Design soll ruhig, vertrauenswürdig, freundlich und klar wirken. Die Oberfläche verwendet bewusst wenige Farben und vermeidet eine überladene oder stark dekorative Gestaltung.

### Erstes Theme

Das erste Theme ist ein helles Standard-Theme mit folgenden Grundprinzipien:

- helle, freundliche Flächen
- hoher Weißanteil
- wenige Akzentfarben
- zurückhaltende Farbgebung passend zu Gesundheit und Apotheke
- klare Hierarchien
- gut lesbare Typografie
- großzügige Abstände
- einfache, verständliche Icons
- ausreichend große Touch-Flächen
- hoher Kontrast und gute Lesbarkeit
- keine rein dekorativen Farbunterschiede für wichtige Zustände

Die konkrete Farbpalette wird separat festgelegt.

### Theme-Fähigkeit von Beginn an

Farben, Abstände, Typografie, Radien, Schatten und andere wiederkehrende Designwerte werden nicht direkt in Komponenten fest codiert.

Stattdessen werden zentrale Design Tokens beziehungsweise CSS Custom Properties verwendet.

Beispiel:

```css
:root {
  --color-background: #ffffff;
  --color-surface: #f7f8f8;
  --color-text: #1f2525;
  --color-text-muted: #667070;
  --color-primary: #3a7d70;
  --color-border: #dfe5e4;

  --radius-small: 0.5rem;
  --radius-medium: 0.75rem;

  --spacing-small: 0.5rem;
  --spacing-medium: 1rem;
  --spacing-large: 1.5rem;
}
```

Komponenten verwenden ausschließlich diese semantischen Variablen und keine apothekenspezifischen Farbwerte.

Beispiel:

```css
.card {
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}
```



### Tailwind CSS und Theme-System

Für das Frontend wird Tailwind CSS verwendet.

Tailwind dient als Grundlage für:

- Layout und Responsive Design
- Abstände und Größen
- Typografie
- Komponenten-Styling
- Zustände und Interaktionen
- mobile-first Gestaltung

Das Theme-System wird trotzdem über semantische Design Tokens aufgebaut. Tailwind-Klassen sollen nicht dazu führen, dass konkrete Markenfarben direkt in allen Komponenten verteilt werden.

Zu vermeiden:

```html
<button class="bg-emerald-600 text-white">
```

Bevorzugt wird eine semantische Theme-Abstraktion, sodass Komponenten beispielsweise mit Rollen wie `primary`, `surface`, `foreground`, `muted`, `border`, `success`, `warning` und `danger` arbeiten.

Die zugrunde liegenden Werte sollen zentral über CSS Custom Properties beziehungsweise die Tailwind-Theme-Konfiguration steuerbar sein.

Dadurch bleiben folgende Erweiterungen möglich:

- Dark Mode
- apothekenspezifische Farben
- unterschiedliche SaaS-Themes
- spätere White-Label-Apps
- zentrale Anpassung des Designs ohne Änderungen an den Fachkomponenten

Tailwind wird damit als Styling-Werkzeug verwendet, während die eigentliche visuelle Identität über das zentrale Theme-System definiert wird.

### Vorbereitung für Dark Mode

Ein Dark Mode ist für eine spätere Entwicklungsphase vorgesehen.

Bereits das erste Theme wird daher so aufgebaut, dass ein alternatives Farbschema ergänzt werden kann, ohne Komponenten neu gestalten zu müssen.

Beispiel:

```css
[data-theme="dark"] {
  --color-background: #111515;
  --color-surface: #1a2020;
  --color-text: #f3f6f5;
  --color-text-muted: #adb8b5;
  --color-primary: #74b7a7;
  --color-border: #303a38;
}
```

Komponenten dürfen daher nicht davon ausgehen, dass Hintergründe immer weiß oder Texte immer dunkel sind.

### Vorbereitung für SaaS-Themes

Langfristig sollen einzelne Apotheken ihr Erscheinungsbild konfigurieren können.

Mögliche konfigurierbare Werte:

- Primärfarbe
- Sekundär- beziehungsweise Akzentfarbe
- Logo
- App-Icon
- gegebenenfalls Schriftfamilie
- Border-Radius beziehungsweise visuelle Ausprägung
- Light- und Dark-Theme

Die fachlichen Komponenten bleiben unabhängig vom jeweiligen Kunden-Theme.

Eine spätere Mandantenkonfiguration könnte beispielsweise folgende Struktur liefern:

```json
{
  "theme": {
    "name": "stadtapotheke",
    "logo": "/assets/tenant/stadtapotheke/logo.svg",
    "colors": {
      "primary": "#39796d",
      "accent": "#dceee9"
    }
  }
}
```

Diese Konfiguration dient ausschließlich der Darstellung. Sicherheits- oder fachliche Logik darf nicht von Theme-Werten abhängig sein.

### UX-Grundsatz

Die Anwendung soll wie eine eigenständige Gesundheits-App wirken und nicht wie eine klassische Website innerhalb eines App-Rahmens.

Mobile Nutzung wird als primärer Anwendungsfall betrachtet. Desktop und Tablet bleiben vollständig nutzbar, werden jedoch ausgehend von einer mobilen Informationsarchitektur entwickelt.

Das Designsystem soll konsistent, zugänglich und wiederverwendbar aufgebaut werden, damit spätere neue Module und kundenspezifische Themes ohne grundlegende Neugestaltung ergänzt werden können.


## Erste UI-Komponenten

### Schlanke Headerbar

Die Anwendung erhält eine kompakte Headerbar als wiederkehrendes Navigationselement.

Bestandteile:

- Hamburger-Menü zum Öffnen der Hauptnavigation
- kleines Apotheken- beziehungsweise App-Logo
- kleines Profilbild des angemeldeten Benutzers
- Klick auf das Profilbild führt direkt zur Profilseite

Grundprinzipien:

- geringe Höhe
- mobile-first
- zurückhaltende Gestaltung
- klare Touch-Flächen trotz kompakter Optik
- keine unnötigen Texte oder zusätzlichen Bedienelemente
- Logo und Profilbild werden theme- beziehungsweise mandantenfähig aufgebaut
- die Headerbar darf keine festen Markenfarben enthalten, sondern verwendet die zentralen Theme-Tokens

Die genaue Positionierung kann im Prototypen visuell getestet werden. Funktional gilt:

```text
Header
├── Hamburger-Menü
├── Mini-Logo
└── Mini-Profilbild → Profilseite
```

Auf kleinen Displays soll die Headerbar möglichst wenig vertikalen Raum beanspruchen und den eigentlichen App-Inhalt nicht dominieren.


### Footerbar / Bottom Navigation

Die mobile Anwendung erhält eine dauerhaft gut erreichbare Footerbar beziehungsweise Bottom Navigation für die wichtigsten App-Bereiche.

Die endgültigen Navigationseinträge werden erst nach Ausarbeitung der Informationsarchitektur festgelegt.

Bereits vorgesehen:

- zentraler beziehungsweise besonders hervorgehobener Zugang zum QR-/Rechnungsscanner
- weitere wichtige Hauptbereiche der App werden später ergänzt

Grundprinzipien:

- mobile-first
- wenige, klar erkennbare Hauptaktionen
- Kombination aus Icon und bei Bedarf kurzer Beschriftung
- ausreichend große Touch-Flächen
- aktive Seite klar erkennbar
- Safe Areas von iOS und Android berücksichtigen
- keine apothekenspezifischen Farben direkt in der Komponente
- Scanner-Aktion darf visuell stärker hervorgehoben werden als normale Navigationseinträge

Vorläufige Struktur:

```text
Bottom Navigation
├── [noch offen]
├── [noch offen]
├── QR-/Rechnungsscanner
├── [noch offen]
└── [noch offen]
```

Die Anzahl und Auswahl der Einträge wird im Prototyp anhand der wichtigsten Kundenabläufe festgelegt. Es sollen nicht vorsorglich Funktionen in die Bottom Navigation aufgenommen werden, die dort später keinen regelmäßigen Zugriff benötigen.


### Seitenweises Loading-Verhalten

Um sichtbare Layoutsprünge beim Laden von Seiten und deren Daten zu vermeiden, wird für Seitenwechsel und initiale Datenladevorgänge ein einheitlicher Loading-Zustand verwendet.

Grundregel:

- Headerbar bleibt sichtbar.
- Footerbar beziehungsweise Bottom Navigation bleibt sichtbar.
- Der eigentliche Seiteninhalt wird während des Ladens vollständig durch einen Loader ersetzt beziehungsweise überdeckt.
- Der Loading-Bereich verwendet zunächst einen weißen beziehungsweise durch das aktuelle Theme definierten Hintergrund.
- In der Mitte des Inhaltsbereichs wird ein klar erkennbarer Loading Spinner angezeigt.
- Der Benutzer soll währenddessen keine teilweise geladenen oder springenden Inhalte sehen.

Konzeptionell:

```text
┌──────────────────────────────┐
│ Headerbar                    │
├──────────────────────────────┤
│                              │
│                              │
│        Loading Spinner       │
│                              │
│                              │
├──────────────────────────────┤
│ Bottom Navigation            │
└──────────────────────────────┘
```

Der Loader bezieht sich auf den jeweiligen Seiteninhalt und nicht auf die gesamte Anwendung.

Auch diese Darstellung muss theme-fähig umgesetzt werden. Statt eines fest codierten weißen Hintergrunds wird langfristig ein semantischer Hintergrundwert wie `--color-background` beziehungsweise ein entsprechender Tailwind-Theme-Token verwendet.

Ziel ist ein ruhiger, stabiler Seitenwechsel ohne sichtbares Nachladen einzelner Komponenten oder nachträgliche Größenänderungen.


## Mandantenstrategie

Zum aktuellen Stand erhält jede Apotheke eine eigene, separat gebrandete App.

Eine zentrale App mit Apothekenauswahl ist für die erste Version nicht vorgesehen.

Das bedeutet für die Benutzeroberfläche:

- keine Apothekenauswahl
- keine Mandantenumschaltung durch Kunden
- keine Auswahl einer Stammapotheke
- Branding und Inhalte beziehen sich immer auf genau eine Apotheke
- Login und Registrierung gelten innerhalb der jeweiligen Apotheken-App

Trotzdem wird die technische Architektur von Beginn an mandantenfähig vorbereitet.

### Grundprinzip

Auch wenn eine App zunächst nur genau eine Apotheke repräsentiert, sollen fachliche Daten intern einer Apotheke beziehungsweise einem Mandanten zugeordnet werden können.

Beispielhafte spätere Struktur:

```text
Tenant / Pharmacy
├── Users
├── CustomerRelations
├── Appointments
├── Rewards
├── Receipts
├── News
├── Messages
└── Settings
```

Die aktuell aktive Apotheke wird in der ersten Version nicht vom Benutzer gewählt, sondern ergibt sich aus der jeweiligen App-Konfiguration.

Beispiel:

```json
{
  "tenant": {
    "id": "stadtapotheke-trofaiach",
    "name": "Stadtapotheke Trofaiach"
  }
}
```

Diese Information kann zunächst fest in der Build- beziehungsweise App-Konfiguration hinterlegt sein und später durch eine zentrale Mandantenauflösung ersetzt werden.

### Sicherheitsgrundsatz

Die Mandanten-ID ist niemals ein ausreichendes Sicherheitsmerkmal.

Das Backend muss später bei jedem Zugriff prüfen:

- welcher Benutzer angemeldet ist,
- zu welchem Mandanten der angefragte Datensatz gehört,
- ob der Benutzer für diesen Mandanten berechtigt ist,
- ob der konkrete Datensatz innerhalb dieses Mandanten sichtbar oder bearbeitbar ist.

Eine vom Frontend übermittelte `tenantId` darf daher nie ungeprüft als Berechtigungsnachweis verwendet werden.

### Ziel

Die erste Version bleibt für Kunden möglichst einfach:

```text
App der Apotheke öffnen
        ↓
Login / Registrierung
        ↓
direkt in die eigene Apotheken-App
```

Die technische Struktur soll jedoch einen späteren Ausbau ermöglichen, beispielsweise:

- zentrale App mit Apothekenauswahl
- Benutzer mit Beziehungen zu mehreren Apotheken
- SaaS-Backend für mehrere Mandanten
- White-Label-Apps auf gemeinsamer Codebasis
- getrennte oder gemeinsame Store-Apps


## Regel für native Gerätefunktionen

Für native Gerätefunktionen gilt folgende Priorität:

1. Offizielle Capacitor-Plugins verwenden
2. Nur wenn kein offizielles Plugin vorhanden oder ausreichend geeignet ist: etabliertes Community-Plugin prüfen
3. Eigenes Capacitor-Plugin beziehungsweise eigener nativer Swift-/Kotlin-Code nur dann entwickeln, wenn keine geeignete bestehende Lösung verfügbar ist

Ziel ist es, die native Codebasis so klein wie möglich zu halten und möglichst viel Funktionalität über offiziell gepflegte Capacitor-Schnittstellen abzudecken.

Typische Bereiche, die bevorzugt über offizielle Capacitor-Plugins umgesetzt werden sollen:

- Barcode-/QR-Scanning
- Kamera
- Push Notifications
- App Lifecycle
- Browser / externe Links
- Device Information
- Network Status
- Filesystem
- Share
- Haptics
- Keyboard
- Status Bar
- Splash Screen

Auch bei Verwendung offizieller Plugins greift die Fachlogik nicht direkt auf Capacitor zu.

Stattdessen werden eigene Angular Services beziehungsweise Adapter verwendet:

```text
Feature-Komponente
        ↓
eigener Angular Service / Adapter
        ↓
offizielles Capacitor Plugin
        ↓
native Plattform
```

Beispiel:

```text
ReceiptScannerComponent
        ↓
BarcodeScannerService
        ↓
@capacitor/barcode-scanner
        ↓
iOS / Android
```

Dadurch bleiben:

- Komponenten testbar
- Browser-/Mock-Implementierungen möglich
- spätere Plugin-Wechsel beherrschbar
- native Details aus der Fachlogik herausgehalten

Eigener nativer Code ist ausdrücklich keine Standardlösung, sondern eine Ausnahme für Funktionen, die sich mit offiziellen oder geeigneten bestehenden Plugins nicht umsetzen lassen.


## KI-gestützte Angular-Entwicklung

Da das Projekt mit KI-Unterstützung entwickelt wird, gelten zusätzlich die offiziellen Angular-Empfehlungen für LLM- und AI-gestützte Entwicklung:

https://angular.dev/ai/develop-with-ai

Die jeweils aktuelle offizielle Angular-Best-Practices-Datei für KI-Werkzeuge soll als zusätzliche Entwicklungsregel in das Repository übernommen und bei der Arbeit mit Codex berücksichtigt werden.

Die Angular-Empfehlungen werden nicht vollständig in dieser Projektdatei dupliziert, da sie vom Angular-Team regelmäßig aktualisiert werden. Die offizielle Quelle hat für allgemeine Angular-Konventionen Vorrang, sofern sie nicht einer bewussten projektspezifischen Entscheidung in dieser Datei widerspricht.

Zum aktuellen Stand gehören insbesondere folgende Grundsätze dazu:

- striktes TypeScript Type Checking
- `any` vermeiden; bei unbekannten Typen `unknown` verwenden
- moderne Standalone Components verwenden
- Signals für lokalen und reaktiven State bevorzugen
- Feature-Routen lazy laden
- Komponenten klein und auf eine Verantwortung begrenzen
- moderne `input()`, `output()`, `model()` und `computed()` APIs verwenden
- neue Formulare bevorzugt mit Signal Forms umsetzen; andernfalls Reactive Forms
- natives Angular Control Flow mit `@if`, `@for` und `@switch` verwenden
- `ngClass` und `ngStyle` vermeiden und stattdessen Class-/Style-Bindings verwenden
- Services nach dem Single-Responsibility-Prinzip strukturieren
- Dependency Injection über `inject()` verwenden
- statische Bilder mit `NgOptimizedImage` behandeln
- Accessibility von Beginn an berücksichtigen, insbesondere WCAG AA und AXE-Prüfungen

### Angular-Kontext für Codex

Zusätzlich stellt Angular speziell für LLMs aufbereitete Dokumentationsdateien bereit:

- `llms.txt` als Index relevanter Angular-Ressourcen
- `llms-full.txt` als umfangreicheren Angular-Kontext

Diese Ressourcen können Codex bei Bedarf als aktuelle Referenz für Angular-Konventionen und APIs dienen.

### Priorität der Regeln

Bei der KI-gestützten Entwicklung gilt:

```text
projektspezifische Entscheidungen in ENTWICKLUNG.md
        ↓
aktuelle offizielle Angular AI Best Practices
        ↓
allgemeine Angular-Dokumentation
```

Bewusste Architekturentscheidungen dieses Projekts – beispielsweise CSR, Tailwind CSS, Capacitor-Abstraktionen oder die Mock-/Repository-Struktur – werden nicht durch allgemeinere AI-Empfehlungen überschrieben.
