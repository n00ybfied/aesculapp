# Terminreservierung – Entwurf

## Ziel und fachliche Begriffe

Das Modul verwaltet buchbare Termine für einen Mandanten. Es trennt bewusst drei Ebenen:

1. **Terminart** – etwa „Impfung“ (10 Minuten) oder „Bachblütenberatung“ (30 Minuten).
2. **Ressource** – zunächst eine Person wie Frau Fink oder Frau Kaiser; später auch Raum, Tisch oder Gerät. Eine Ressource kann mehrere Terminarten anbieten.
3. **Verfügbarkeitsfenster** – ein konkreter Zeitraum einer Ressource, zum Beispiel Montag 10:00–13:00 und 14:00–17:00.

Termine überschneiden sich nie innerhalb derselben Ressource. Unterschiedliche Ressourcen sind absichtlich parallel buchbar. Damit sind die im Beispiel genannten Impfungen und Beratungen gleichzeitig möglich, ohne die spätere Restaurant-/Raumnutzung technisch auszuschließen.

## Datenmodell

```text
AppointmentType
  tenant, title, description?, durationMinutes, bufferBeforeMinutes,
  bufferAfterMinutes, isVisible, bookingFrom?, bookingUntil?

BookableResource
  tenant, name, description?, type (person | room | table | equipment), isActive

ResourceAppointmentType
  resource, appointmentType

ResourceAvailabilityRule
  resource, weekdays, startsAt, endsAt, validFrom?, validUntil?, isActive

ResourceAvailabilityException
  resource, date, startsAt?, endsAt?, kind (closed | extra_open)

CalendarClosure
  tenant, startsAt, endsAt, reason?, appliesToAllResources = true

Appointment
  tenant, resource, appointmentType, customer,
  startsAt, endsAt, status (reserved | cancelled | completed | no_show),
  customerNote?, internalNote?, createdAt, cancelledAt?, emailNotificationRequested?
```

Die Dauer eines Termins wird bei der Buchung als Zeitraum (`startsAt`/`endsAt`) gespeichert; spätere Änderungen an einer Terminart verändern keine bestehenden Reservierungen. Pufferzeiten werden für die Kollisionsprüfung berücksichtigt, aber nicht zwingend dem Kunden angezeigt.

## Verfügbarkeit und Buchung

Der Server erzeugt Slots erst für eine konkrete Kombination aus Tag, Terminart und Ressource. Ein Slot ist verfügbar, wenn er vollständig in einem aktiven Verfügbarkeitsfenster liegt und weder ein vorhandener Termin noch dessen Pufferzeit überlappt.

Die Buchung erhält einen eigenen transaktionalen API-Endpunkt. Er sperrt die betroffene Ressource beziehungsweise deren Terminbereich in der Datenbank, berechnet den Slot erneut und legt den Termin nur an, wenn er weiterhin frei ist. Der vom Client angezeigte Slot ist damit nie eine Berechtigung.

Der Kunde wählt nie selbst eine Ressource. Der Server ermittelt aus allen zur Terminart passenden Ressourcen einen freien Slot und wählt bei mehreren Möglichkeiten deterministisch die Ressource mit der frühesten passenden Verfügbarkeit beziehungsweise mit der geringsten Terminlast. In der Terminbestätigung und unter „Meine Termine“ wird die zugeordnete Person angezeigt.

Das Standard-Buchungsfenster beginnt sofort und reicht 14 Tage in die Zukunft. Diese Obergrenze ist als mandantenbezogene Einstellung überschreibbar, nicht als fest verdrahteter Clientwert. Kunden dürfen bis 24 Stunden vor Beginn selbst stornieren; danach zeigt die App die Telefonnummer beziehungsweise den Kontaktweg der Apotheke an. Die API erzwingt diese Frist unabhängig von der Benutzeroberfläche.

Vorgeschlagene API:

```text
GET  /api/v1/appointments/calendar?typeId=&month=
GET  /api/v1/appointments/slots?date=&typeId=
POST /api/v1/appointments
GET  /api/v1/appointments/mine
POST /api/v1/appointments/{id}/cancel

GET/POST/PATCH /api/v1/admin/appointment-types
GET/POST/PATCH /api/v1/admin/appointment-resources
GET/POST/PATCH /api/v1/admin/appointment-availability
GET              /api/v1/admin/appointments
POST             /api/v1/admin/appointments/{id}/cancel
```

## Kundenablauf

1. Kunde wählt eine Terminart.
2. Monatskalender zeigt pro Tag eine Ampel: grau = kein Slot, grün = überwiegend frei, gelb = wenige Slots, rot = ausgebucht. „Rot“ sollte nur verwendet werden, wenn grundsätzlich Angebote an diesem Tag existieren, aber alle Slots vergeben sind.
3. Nach einem Tagklick erscheint eine Liste freier Uhrzeiten, optional mit der anbietenden Person.
4. Kunde bestätigt den Slot und sieht danach Termin, zugeteilte Person und Stornierungsoption gemäß Mandantenregel.

Die genaue Ampelgrenze wird nicht fest in die App eingebaut. Vorschlag: grün bei mindestens 50 % freien Slots, gelb bei 1–49 %, rot bei 0 %, grau bei keinem geplanten Angebot.

## Adminablauf

- Terminarten: Titel, Dauer, optionale Beschreibung, Sichtbarkeit, Buchungszeitraum.
- Ressourcen: Personen anlegen; je Ressource angebotene Terminarten zuordnen.
- Verfügbarkeit: wiederkehrende Vor- und Nachmittagsfenster für einen oder mehrere Wochentage (zum Beispiel Mo–Fr) sowie Ausnahmen.
- Kalendersperren: Mandantenweite Sperren für einen ganzen Tag, Teil eines Tags oder einen längeren Zeitraum; sie übersteuern die normalen Ressourcenzeiten. Beispiel: 24.12., 13:00–23:59 ohne Termine.
- Kalender: Tages-/Wochenansicht als Spalten pro Ressource, ähnlich Google Calendar; Filter nach Terminart und Ressource. Ein Termin blockiert ausschließlich seine Spalte.
- Mitarbeitende können Termine anlegen, verschieben, stornieren und als erledigt beziehungsweise „nicht erschienen“ markieren. Bei Anlegen, Verschieben oder Stornieren steht eine standardmäßig aktivierte Checkbox „Kunden per E-Mail informieren“ bereit. Nur mit dieser ausdrücklichen Auswahl wird die Benachrichtigung versendet; interne Korrekturen bleiben damit still möglich.

## Beschlossene Standardwerte

| Einstellung | Standard | Mandanten-Override |
| --- | --- | --- |
| Buchungsvorschau | 14 Tage | ja |
| Kundenstornierung | bis 24 Stunden vor Termin | ja |
| Puffer nach Termin | 5 Minuten | je Terminart |
| Ressourcenauswahl | serverseitig automatisch | nein |

## Datenschutz und Benachrichtigungen

Der erste Stand speichert nur den Termin, Terminart, Zeitpunkt, Ressource und eine optionale kurze Kundenanmerkung. Medizinische Details oder Diagnosen gehören nicht in ein Buchungsformular. E-Mail- oder Push-Bestätigung, Erinnerung und Stornierungsbenachrichtigung werden erst nach Festlegung der rechtlichen Texte und der Fristen ergänzt.
