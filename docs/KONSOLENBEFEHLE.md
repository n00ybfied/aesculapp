# Wichtige Konsolenbefehle

Diese Befehle werden im Projektverzeichnis `G:\aesculapp` in PowerShell ausgeführt, sofern nicht anders angegeben.

## Lokale Entwicklung mit Laragon

Laragon muss für PHP und MariaDB gestartet sein. Der folgende Befehl startet Kunden-App, Adminportal und API gemeinsam. Bereits von diesem Projekt gestartete Entwicklungsprozesse werden dabei beendet und neu gestartet.

```powershell
.\start-dev.ps1
```

Danach sind die Anwendungen erreichbar:

| Anwendung | Adresse |
| --- | --- |
| Kunden-App | `http://localhost:4200` |
| Adminportal | `http://localhost:4201` |
| API-Gesundheitscheck | `http://localhost:6080/api/health` |

Die relevanten lokalen Logs liegen unter `server\var\log\`:

```powershell
Get-Content server\var\log\php-server.log -Tail 100
Get-Content server\var\log\dev.log -Tail 100
Get-Content server\var\log\qr.log -Tail 100
```

## Angular bauen und testen

Angular benötigt die im Projekt abgelegte Node-Version. Die erste Zeile setzt sie nur für das aktuelle PowerShell-Fenster.

```powershell
$env:Path = 'G:\aesculapp\.runtime\node\node_modules\node\bin;' + $env:Path
```

Kunden-App bauen:

```powershell
npm.cmd --prefix client run build
```

Adminportal bauen:

```powershell
npm.cmd --prefix admin run build
```

Kunden-App für die öffentliche Demo bauen:

```powershell
npm.cmd --prefix client run build:demo
```

Angular-Tests ausführen:

```powershell
npm.cmd --prefix client run test
npm.cmd --prefix admin run test
```

## Datenbank und Symfony

Die Befehle in diesem Abschnitt werden im Server-Ordner ausgeführt:

```powershell
Set-Location server
```

Ausstehende Datenbankmigrationen anwenden:

```powershell
php bin/console doctrine:migrations:migrate
```

Container und PHP-Dateien prüfen:

```powershell
php bin/console lint:container
php bin/console lint:yaml config
php -l src\Controller\ApiPointsController.php
```

Die letzte Zeile ist ein Beispiel für die Syntaxprüfung einer einzelnen geänderten PHP-Datei.

Regressionstests ausführen:

```powershell
php tests/receipt-integration.php
php tests/chat-integration.php
php tests/authorization-integration.php
php tests/web-push.php
```

## Grund- und Demo-Daten

Den Mandanten „Stadtapotheke Trofaiach“ inklusive Prototyp-Kunde und Adminzugang anlegen. Der Befehl ist für vorhandene Einträge geeignet und legt diese nicht doppelt an.

```powershell
php bin/console app:seed:sta
```

Fiktive Apothekennews, Gutscheine und Prämien einschließlich der versionierten Demobilder für Pagination und Filtertests anlegen. Vorhandene Demo-Einträge bleiben erhalten; der Befehl ist wiederholbar.

```powershell
php bin/console app:seed:demo-catalog
```

Nur bei bereits vorhandenen Katalogeinträgen ohne Bild können die jeweiligen Demobilder ergänzt werden:

```powershell
php bin/console app:seed:demo-images
```

## Wiederkehrende Hintergrundaufgaben

Geburtstagspunkte werden nicht beim Öffnen des Dashboards gebucht. Der folgende Befehl gehört auf dem Produktivserver in einen täglichen Cronjob, idealerweise frühmorgens:

```powershell
php bin/console app:award-birthday-bonuses
```

Terminerinnerungen für alle am folgenden Tag stattfindenden reservierten Termine versenden. Der Befehl sendet eine transaktionale E-Mail sowie – falls vom Kunden aktiviert und auf einem Gerät eingerichtet – eine Push-Nachricht. Jede erfolgreiche Erinnerung wird je Kanal am Termin gespeichert und nicht doppelt versendet. Der Produktivserver soll ihn täglich, zum Beispiel um 09:00 Uhr, ausführen:

```powershell
php bin/console app:appointments:send-reminders
```

Ausstehende Chat-Push-Benachrichtigungen erneut zustellen:

```powershell
php bin/console app:push:send
```

## Einmalige Schlüssel-Ersteinrichtung

Diese Befehle nur bei einer neuen, leeren Installation ausführen. Bestehende Schlüssel niemals neu erzeugen oder überschreiben: Verschlüsselte Daten beziehungsweise bestehende Push-Abonnements wären sonst nicht mehr nutzbar.

Privaten Verschlüsselungsschlüssel für Chat- und Medikamentendaten erzeugen:

```powershell
php bin/console app:private-data:initialize-key
```

VAPID-Schlüssel für Web-Push erzeugen:

```powershell
php bin/console app:push:initialize-key
```

Falls die Schlüsselerzeugung unter Windows wegen OpenSSL fehlschlägt, muss `OPENSSL_CONF` auf die lokale Laragon-OpenSSL-Konfiguration zeigen. Schlüsseldateien gehören nicht in Git und müssen beim Deployment separat gesichert werden.

## Nützliche Übersicht

Alle verfügbaren Symfony-Befehle anzeigen:

```powershell
php bin/console list
```

Hilfe zu einem einzelnen Befehl anzeigen:

```powershell
php bin/console app:seed:demo-catalog --help
```
