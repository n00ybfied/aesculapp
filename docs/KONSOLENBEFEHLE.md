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
php tests/profile-completion-bonus.php
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

Für die separate Apotheken-Testinstallation gibt es einen kleineren, ausdrücklich als fiktiv markierten Katalog mit 12 Inhalten, 8 Gutscheinen und 8 Prämien. Nur nach Datenbankbackup und nur auf dem Testserver manuell ausführen; weder Deployment noch der normale Demo-Seed starten ihn automatisch. Bestehende Einträge mit demselben Titel werden nicht überschrieben.

```bash
cd /home/.sites/95/site1646665/web/aesculapp-api
APP_ENV=prod APP_DEBUG=0 php84 bin/console app:seed:apotheke-test-catalog --confirm
```

Die Bilder liegen unter `public/uploads/demo` und werden beim Apotheken-Deploy separat mitkopiert. Neue Testeinträge sind in der Kunden-App sichtbar und können dort testweise eingelöst werden; sie stellen keine realen Angebote dar.

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

Ausstehende Chat-Push-Benachrichtigungen und neue Apotheken-News verarbeiten:

```powershell
php bin/console app:push:send
```

Der Befehl verarbeitet neben ausstehenden Chat-Push-Nachrichten auch neue, sichtbare Apotheken-News und deren ausstehende Empfängerzustellungen. Für geplante News muss er regelmäßig laufen. Die Auswahl wird beim ersten Versandlauf pro Beitrag fixiert; Bearbeiten verschickt nicht erneut.

Für die Demo auf dem VPS und die Apotheken-Testinstallation gemeinsam liegt das Linux-Skript
[`ops/scheduler/run-maintenance.sh`](../ops/scheduler/run-maintenance.sh) vor.
Es führt je Aufruf genau einen dieser drei Jobs zuerst lokal auf dem VPS und danach per SSH
auf World4You aus. Ein Fehler auf einem Server verhindert den Versuch auf dem anderen nicht;
der Gesamtaufruf liefert dann einen Fehlercode. Der `check`-Modus prüft Verbindung, PHP
und Symfony-Konsole ohne fachliche Daten zu verändern.

Das Skript unter dem VPS-Benutzer `aneger` zum Beispiel als
`/home/aneger/bin/aesculapp-maintenance` mit ausführbaren Rechten (`chmod 700`)
installieren. Der private Schlüssel muss unter
`/home/aneger/.ssh/aesculapp_scheduler` liegen und nur für diesen Benutzer lesbar sein.
Den SSH-Hostkey vorab unabhängig verifizieren und in `known_hosts` hinterlegen;
das Skript akzeptiert unbekannte Hostkeys absichtlich nicht. Vor dem Einrichten von Cron:

```bash
/home/aneger/bin/aesculapp-maintenance check
```

Beispiel für `crontab -e` auf dem VPS (Server-Zeitzone zuvor mit `date` prüfen;
für die folgenden Zeiten wird Europe/Vienna bzw. Europe/Berlin vorausgesetzt):

```cron
*/5 * * * * /home/aneger/bin/aesculapp-maintenance push >> /home/aneger/.local/state/aesculapp-scheduler/cron.log 2>&1
5 6 * * * /home/aneger/bin/aesculapp-maintenance birthday >> /home/aneger/.local/state/aesculapp-scheduler/cron.log 2>&1
0 9 * * * /home/aneger/bin/aesculapp-maintenance reminders >> /home/aneger/.local/state/aesculapp-scheduler/cron.log 2>&1
```

Das Logverzeichnis vorher mit `mkdir -p /home/aneger/.local/state/aesculapp-scheduler`
anlegen und auf Benutzerzugriff begrenzen (`chmod 700`). Das Log regelmäßig rotieren.
Ein eigener `flock` pro Job verhindert überlappende Läufe auf dem VPS. Das Skript
erfordert `bash`, `flock`, `ssh` und lokales PHP 8.4; auf World4You wird `php84`
verwendet. Es enthält keine Passwörter oder Datenbank-Zugangsdaten. Bei späteren
Pfad- oder Benutzeränderungen die Konstanten am Anfang des Skripts anpassen.

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
