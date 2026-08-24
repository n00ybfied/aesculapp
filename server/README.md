# Aesculapp API

Symfony-API für Aesculapp. Die Anwendung verwendet lokal MariaDB oder MySQL über Laragon.

## Lokale Einrichtung

1. In Laragon eine Datenbank namens aesculapp mit Zeichensatz utf8mb4 anlegen.
2. .env.local.example nach .env.local kopieren.
3. In .env.local Benutzer, Passwort und die tatsächliche MariaDB- beziehungsweise MySQL-Version eintragen.
4. Nach den ersten Entities werden Migrationen mit php bin/console doctrine:migrations:migrate ausgeführt.

.env.local enthält lokale Zugangsdaten und bleibt unversioniert.

## Erste Prüfung

Mit php bin/console debug:router api_health lässt sich der Endpunkt prüfen.

Port 6000 ist in Chromium-basierten Browsern gesperrt. Der Entwicklungsserver läuft deshalb unter http://localhost:6080 und wird mit folgendem Befehl gestartet:

php -S localhost:6080 -t public public/index.php

Der erste öffentliche Entwicklungsendpunkt lautet GET /api/health und antwortet mit dem API-Status. CORS ist für lokale localhost- und 127.0.0.1-Ursprünge konfiguriert, damit das Angular-Frontend ihn während der Entwicklung erreichen kann.

## Anmeldung

POST /api/v1/auth/login erwartet Benutzername und Passwort als JSON. Bei gültigen Zugangsdaten liefert die API einen signierten Access Token mit 15 Minuten Laufzeit und die minimalen Profildaten. Der lokale Prototyp-Zugang lautet kunde / trofaiach.

## Konto und Passwort

Die öffentliche Auth-API bietet folgende Endpunkte:

- POST /api/v1/auth/register – Name, Benutzername, E-Mail-Adresse und Passwort registrieren und direkt anmelden.
- POST /api/v1/auth/password-reset/request – einen neutral beantworteten Passwort-Link anfordern.
- POST /api/v1/auth/password-reset/confirm – ein neues Passwort mit einem einmaligen, 60 Minuten gültigen Reset-Token setzen.

Die Mandantenzuordnung einer gebrandeten App kommt aus APP_TENANT_SLUG in .env.local beziehungsweise aus der Produktionsumgebung, nie aus einem Request. Für den lokalen Versand ist standardmäßig MAILER_DSN=null://null gesetzt; zum Testen mit einem Mailserver ist in .env.local ein SMTP-DSN einzutragen.
