# AesculApp – Projektkontext

Stand: 16. September 2026. Diese Datei beschreibt den aktuellen technischen und fachlichen Stand; sie ersetzt keinen Gesprächsverlauf.

## Einstieg

- Lies vor Änderungen unbedingt [`AGENTS.md`](AGENTS.md) und [`docs/ENTWICKLUNG.md`](docs/ENTWICKLUNG.md). Sie enthalten verbindliche Architektur- und UX-Regeln.
- Das Repository enthält drei Anwendungen: `client/` (Kunden-App), `admin/` (Apothekenportal) und `server/` (Symfony-API).
- Der aktuelle Arbeitsbaum enthält **nicht committe Änderungen**, insbesondere das noch unfertige Terminmodul. Bestehende Änderungen niemals mit `git reset`, `git checkout --` oder ähnlichem verwerfen.
- Keine Zugangsdaten, Schlüssel oder produktiven `.env.local`-Dateien committen oder in Dokumentation aufnehmen.

## Architektur und Grundentscheidungen

### Stack

| Bereich | Technologie | Entscheidung |
| --- | --- | --- |
| Kunden-App | Angular, CSR, Tailwind, PWA/Capacitor vorbereitet | Mobile-first; eine Codebasis für Browser und spätere iOS-/Android-Builds. |
| Adminportal | separate Angular-Anwendung | Desktop-orientiert, aber responsiv; getrennte Authentifizierung und Build-Auslieferung. |
| API | Symfony 8, REST, MariaDB/MySQL, Doctrine | API ist alleinige Persistenz- und Berechtigungsinstanz. |
| Medien | lokales Symfony-Upload-Verzeichnis + `MediaAsset` | Öffentliche Mandantenmedien werden zentral wiederverwendet; private Kundenmedien nie dort ablegen. |
| E-Mail | Symfony Mailer über `TenantMailer` | Mandanten-SMTP möglich, Passwort verschlüsselt; Versandfehler dürfen Kerntransaktionen nicht zurückrollen. |

### Mandanten und Sicherheit

- `Tenant`, `User` und `TenantMembership` sind getrennt. E-Mail ist die globale Login-Kennung; Rollen gehören zur Mitgliedschaft, nie global zum User.
- Aktuell wird der Mandant aus Build-/Serverkonfiguration (`APP_TENANT_SLUG`) bestimmt. Eine Mandanten-ID aus dem Client ist niemals vertrauenswürdig.
- Jeder geschützte Endpunkt muss Mitgliedschaft und Mandant serverseitig prüfen. Bekannte IDs allein dürfen keinen Fremdzugriff ermöglichen.
- Kunden-Access-JWTs leben 15 Minuten nur im Speicher. Rotierende Refresh-Tokens liegen als HttpOnly-Cookie vor; Hard Reload erneuert damit den Access Token.
- Admin-Sitzungen gelten nur für die Browser-Session. HTTP-Interceptor beider Angular-Apps leiten bei 401 zum Login.
- Öffentliche Katalogendpunkte sind gezielt in `server/config/packages/security.yaml` freigegeben (z. B. News, Branding, Prämien, Gutscheine). Neue öffentliche Endpunkte dort ergänzen; ansonsten liefert Symfony 401.

### UI-/Codekonventionen

- Angular: Standalone Components, Lazy Routes, `inject()`, Signals für lokalen State, striktes TypeScript. Fachseiten sprechen über Services/Repositories, nicht direkt über technische Details.
- Tailwind ist in der Kunden-App primär. Verwende semantische Theme-Tokens/CSS-Variablen statt Marken-Farbwerte in Fachkomponenten.
- Adminseiten verwenden vorhandene `--admin-*`-Tokens und sollen responsive bleiben.
- Buttons immer mit `cursor: pointer`; ausreichend große Touch-Flächen; Fehler sichtbar und verständlich anzeigen.
- Pflichtfelder: mit `*` unmittelbar **inline** im Label markieren. Nach Submit ungültige Felder rot markieren und konkrete Fehlermeldung zeigen.
- Rich-Text-Inhalte nur über `RichTextEditorComponent` und serverseitig über `RichTextSanitizer` speichern. Im Client nie Angular-Sanitization umgehen.

## Lokale Entwicklung und Verifikation

### Übliche Endpunkte

- Kunden-App: `http://localhost:4200`
- Adminportal: `http://localhost:4201`
- Symfony: `http://localhost:6080`
- Mailpit: `http://localhost:8025`

Vorhandene PowerShell-Skripte im Projektwurzelverzeichnis starten Dienste, aktualisieren die Remote-DB oder führen Git-Deploy-Abläufe aus. Vor dem Ergänzen prüfen, ob die bestehenden Skripte genutzt werden können.

### Prüfungen

```powershell
cd server; php bin/console doctrine:migrations:migrate --no-interaction
cd server; php bin/console lint:container
cd client; npm.cmd run build
cd admin; npm.cmd run build
cd server; php tests/receipt-integration.php
cd server; php tests/chat-integration.php
cd server; php tests/authorization-integration.php
```

Bekannte, derzeit tolerierte Buildwarnungen: `chat.page.ts` überschreitet das CSS-Budget geringfügig; Leaflet ist CommonJS; `tenant-branding.component.css` überschreitet das Admin-CSS-Budget. Kein Buildfehler.

## Implementierte Bereiche

### Authentifizierung und Nutzer

- Login, Registrierung mit Double-Opt-In, erneutes Senden der Bestätigung, Passwort-Reset.
- Abgelaufene/unbestätigte Konten können über den geschützten HTTP-Cron bereinigt werden.
- Mitarbeiterverwaltung im Adminportal: Einladung per E-Mail, Selbstvergabe eines Passworts; bestehende Kunden können dieselbe E-Mail zusätzlich als Mitarbeiter verwenden.
- Kundenprofil: Kontaktdaten, Geburtsdatum, Profilbild-Crop und Benachrichtigungseinstellungen.

Wichtige Dateien: `server/src/Controller/ApiRegistrationController.php`, `ApiLoginController.php`, `ApiRefreshController.php`, `ApiProfileController.php`, `ApiAdminUserController.php`, `client/src/app/core/auth/`, `admin/src/app/core/auth/`.

### Punkte, QR und Prämien

- Punkte sind ein unveränderliches Ledger über `PointAccount`/`PointTransaction`; nie einen Punktestand direkt speichern oder ändern.
- Scanner liest Loyalty-QR-Codes, prüft Präfix und verarbeitet Dubletten serverseitig. Rezeptpflichtige Produkte sind über den gesonderten Kassen-QR bereits ausgeschlossen.
- Eine Prämieneinlösung kann mehrere Positionen/Mengen enthalten; sie sperrt das Punktkonto bzw. den Familienpool fünf Minuten als `ActiveRedemption`. Abbruch erzeugt Gegenbuchungen.
- Prämien (`Reward`) besitzen Sichtbarkeit und optionalen Verfügbarkeitszeitraum (`availableFrom`, `availableUntil`). Die Kundenliste filtert serverseitig; auch die Einlösung prüft die Verfügbarkeit erneut.
- Admin-Prämieneditor: Rich Text, Medienpicker, Pflichtfeld-Markierung, Zeitraum und Sichtbarkeit.

Wichtige Dateien: `server/src/Controller/ApiRewardController.php`, `ApiRedemptionController.php`, `ApiLoyaltyReceiptController.php`, `client/src/app/core/rewards/`, `admin/src/app/features/rewards/`.

### Gutscheine

- Gutscheine (`Coupon`) sind bewusst **getrennt** von Punkte-Prämien. Sie benötigen keine Punkte und sollen später je Kunde einmal mit demselben fünfminütigen Vorzeigeablauf eingelöst werden.
- Aktuell umgesetzt: eigene Entität/API, Adminliste/-editor, Gutscheinheft in der Kunden-App, Sichtbarkeit und optionaler Verfügbarkeitszeitraum, Rich Text und Bild aus der Mediathek.
- Ein Gutscheinbild ist optional; die Auswahl erfolgt über den gemeinsamen `MediaPickerComponent`, der Upload und Mediathek kombiniert. API prüft den Besitz des `MediaAsset` am aktuellen Mandanten.
- **Noch offen:** Einmal-Einlösung, Anzeige eines bereits eingelösten Gutscheins, aktive Gutschein-Einlösung und Adminabbruch. Nicht versehentlich die Prämien-Punkte-Logik für Gutscheine wiederverwenden.

Wichtige Dateien: `server/src/Entity/Coupon.php`, `server/src/Controller/ApiCouponController.php`, `admin/src/app/features/coupons/`, `client/src/app/features/coupons/`.

### Inhalte, Medien und Branding

- News mit Listen-/Detailansicht, Sichtbarkeit, Anzeigezeitraum, Beitragsbild und bereinigtem Rich Text.
- Medienbibliothek mit Mehrfachupload/Drag-and-Drop; öffentliche Bilder werden als JPEG optimiert, auf höchstens 1.400 px Breite begrenzt. Löschen nur, falls unverwendet; Nutzungsanzeige verlinkt zum betroffenen Editor.
- Mandantenbranding: Logo, quadratisches Logo, Favicon. Kunden- und Admin-App laden Mandantenbranding, haben aber lokale Fallbacks (`LOGO`, `L`, Favicon).
- App-Start-Hinweis: Mandantenweit konfigurierbar, Rich Text; normales Schließen nur für die Session, „Nicht mehr anzeigen“ lokal 30 Tage und inhaltsgebunden.

Wichtige Dateien: `server/src/Controller/ApiNewsController.php`, `ApiAdminMediaController.php`, `ApiAdminBrandingController.php`, `server/src/Service/RichTextSanitizer.php`, `admin/src/app/shared/media-picker.component.ts`, `admin/src/app/shared/rich-text-editor.component.ts`.

### Apotheke, Chat, Familie, Medikamente und Push

- Kontaktseite: Adresse, Telefon, E-Mail, Öffnungszeiten, Leaflet/OpenStreetMap, Google-Maps-Link, Rich Text. Kartencontainer muss unter der Footer-Navigation bleiben.
- Externe Website: optionale Mandanten-URL in einem iFrame, externer Link; keine Manipulation fremder iFrame-Inhalte. Menüeintrag nur bei URL.
- Chat: kundenseitig WhatsApp-artig, adminseitig Ticketansicht. Texte/Anhänge sind serverseitig mit XChaCha20-Poly1305 verschlüsselt, nicht Ende-zu-Ende. Polling nur für offene Gespräche.
- Familien: beidseitige Einladungen/Freigaben für Medikamente; optionale gemeinsame Punkte. Familienpool ist rechnerisch, nicht als übertragenes Guthaben; proportional mit Ganzzahl-Rundung buchen.
- Medikamentendaten und private Anhänge verschlüsselt. Schlüssel außerhalb des Deployments über `PRIVATE_DATA_KEY_FILE`, nie in Git.
- Web Push für Browser/PWA mit VAPID; Kategorien im Profil. Push-Inhalte enthalten keine Gesundheitsdaten. Native lokale Medikamentenerinnerungen sind bewusst für Capacitor verschoben.

## Mandanteneinstellungen

`Tenant` enthält derzeit u. a. Startguthaben, Geburtstagsbonus, Punkte/Euro, QR-Präfix/Debug, Familienpunkte, SMTP, Branding, Website, Kontakt und App-Start-Hinweis. Einstellungen sind unter `admin/src/app/features/settings/tenant-branding.component.*`; API: `ApiAdminBrandingController` und `ApiContactController`.

Bei neuen mandantenweiten Regeln prüfen, ob sie zum Tenant gehören statt globalen Konfigurationswerten. Kritische Änderungen (z. B. Punkte/Euro) brauchen UI-Schutz/Bestätigung.

## Terminreservierung – laufender Ausbau

Design ist in [`docs/TERMINE_DESIGN.md`](docs/TERMINE_DESIGN.md) dokumentiert und fachlich abgestimmt.

### Bereits angelegt, aber noch nicht produktionsreif

- Entitäten: `AppointmentType`, `AppointmentResource`, `AppointmentAvailability`, `Appointment`.
- Migration: `server/migrations/Version20260916100000.php`, lokal bereits ausgeführt.
- API-Grundlage: `ApiAppointmentController.php`.
  - Kunden: Terminarten, freie Slots, Buchung, eigene Termine, Stornierung mit 24-Stunden-Frist.
  - Admin: Terminarten, Ressourcen und Wochentagsverfügbarkeit anlegen.
  - Buchung sperrt die Ressource pessimistisch und prüft den Slot erneut.
- Erste Kundenseite `/termine`: Terminart → Tag → freie Slots.
- Erste Adminseite `/termine`: Terminarten, Personen und Mo–Fr-Verfügbarkeit anlegen.
- `ApiAdminAppointmentController` bietet Liste und Stornierung; bei `notifyCustomer: true` wird über `TenantMailer` eine Storno-E-Mail versendet.

### Zwingend als Nächstes fertigstellen

1. **Kalendersperren:** Entität/API/UI für mandantenweite Sperren eines Tages, Teil-Tages oder Zeitraums; sie müssen die Slot-Berechnung übersteuern.
2. **Richtige Admin-Kalenderansicht:** Tages-/Wochenansicht mit Spalten pro Ressource, Filter nach Ressource/Terminart, sichtbaren Zeitachsen und vorhandenen Terminen.
3. **Admin-Terminbearbeitung:** manuell anlegen, verschieben, Status `completed`/`no_show`, stornieren. Bei Änderung Checkbox „Kunden per E-Mail informieren“, standardmäßig aktiv. Der aktuelle API-Storno unterstützt dies bereits, die UI noch nicht.
4. **Einstellungen:** Buchungsvorschau (Default 14 Tage) und Selbststorno-Frist (Default 24 h) mandantenbezogen verwalten; die API verwendet derzeit fest `+14 days`/`+24 hours`.
5. **Verfügbarkeit verbessern:** Mehrfachauswahl tatsächlich im UI, Vormittag/Nachmittag, bearbeiten/löschen, Urlaub/Ausnahmen. Die aktuelle Adminseite speichert nur fix Mo–Fr.
6. **Kundenkalender:** Monatsansicht mit Ampelwerten: grau keine Verfügbarkeit, grün ≥50 % frei, gelb 1–49 %, rot ausgebucht. Aktuell ist es nur ein Datumseingabefeld mit Slotliste.
7. **Robustheit:** Ausnahmebehandlung im Angular-Admin, fachliche Tests für Überschneidungen, Mandantentrennung, Puffer, Stornofristen und parallele Buchungen.

Terminarten/Ressourcen sind bewusst generisch, damit später Räume, Geräte oder Restauranttische möglich sind. Der Kunde wählt nie selbst eine Person; der Server wählt eine freie passende Ressource und zeigt sie nach der Buchung an.

## Bekannte Probleme und technische Schulden

- Das Terminmodul ist ein unfertiger MVP und darf nicht als abgeschlossen kommuniziert werden (siehe Liste oben).
- Die neu angelegten Termin-Dateien sind sehr kompakt formatiert. Beim nächsten Ausbau in normale, gut getestete Klassen/DTOs/Services überführen statt noch mehr Controllerlogik anzuhäufen.
- Für allgemeine Formulare ist die Pflichtfeld-/Fehlermeldungs-Konvention noch nicht flächendeckend nachgezogen. Prämien und Gutscheine dienen als aktuelle Referenz.
- Coupon-Medien sind fertig auswählbar; Coupon-Einlösung fehlt vollständig.
- Remote Deployment/SSH kann aufgrund externer Brute-Force-Last instabil sein. Fail2ban ist aktiv; keine anderen Node-Prozesse auf dem Server stoppen.
- Composer auf dem Remote-Server war veraltet; Deployment überträgt `vendor/` aus GitHub Actions. Remote Composer nicht als Voraussetzung für reguläre Deploys behandeln.

## Bewusst nicht machen

- Keine direkte Client-Persistenz als Sicherheitsbasis; Local Storage nur für klar bezeichnete Prototyp-/UI-Zustände.
- Kein Zugriff auf oder CSS-Manipulation fremder Webseiten in iFrames.
- Keine E2E-Behauptung für Chat/Medikamente; die aktuelle Verschlüsselung ist serverseitig.
- Keine allgemeinen, minutengenauen PWA-Cronjobs für Medikamentenerinnerungen; native lokale Benachrichtigungen erst im Capacitor-Build.
- Keine Mandantenauswahl in der Kunden-App; der Tenant ergibt sich aus Konfiguration.
- Keine Bilder privater Kundenbereiche in der öffentlichen Mediathek.

## Wichtige Infrastrukturdateien

- `server/config/packages/security.yaml` – Firewalls und explizit öffentliche Endpunkte.
- `server/config/services.yaml` – stringbasierte Serviceargumente, z. B. `mailFrom`, Client-URLs und Secrets.
- `ops/nginx/` – Nginx-Vorlagen für Kundenseite, Admin und API.
- `.github/workflows/deploy-demo.yml` – baut Angular-Apps und deployt Server inklusive `vendor/`.
- `docs/ENTWICKLUNG.md` – ausführliche verbindliche Entscheidungen und ergänzende Sicherheitsdetails.
