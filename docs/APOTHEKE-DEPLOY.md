# Apotheken-Testinstallation auf World4You

Diese Installation ist von der öffentlichen Demo und von WordPress getrennt. Der Branch `main` bedient weiterhin die Demo; `apotheke-test` ist der bewusst und nur gelegentlich aktualisierte Stand für die Apotheke.

## Verzeichnisse und Subdomains

Die folgenden Verzeichnisse sind auf `www21.world4you.com` unter `/home/.sites/95/site1646665` angelegt:

| Subdomain | Zielverzeichnis im World4You-Panel | Inhalt |
| --- | --- | --- |
| `app.stadtapotheke-trofaiach.at` | `/aesculapp-client/` | Kunden-App |
| `admin.stadtapotheke-trofaiach.at` | `/aesculapp-admin/` | Admin-App |
| `api.stadtapotheke-trofaiach.at` | `/aesculapp-api/public/` | Ausschließlich öffentlicher API-Einstieg und Uploads |

Die vollständige Symfony-Anwendung liegt unter `/home/.sites/95/site1646665/web/aesculapp-api`, wie lokal mit `public` als einzigem Webroot. World4Yous PHP-`open_basedir` erlaubt keinen Zugriff auf das zuvor verwendete Geschwisterverzeichnis `aesculapp-server` außerhalb von `web`. Das Projektverzeichnis `aesculapp-api` wird zusätzlich durch eine `.htaccess` gesperrt. **Nie** die API-Subdomain auf `/aesculapp-api/` zeigen lassen, sobald dort `.env.local`, Schlüssel und `vendor` liegen. Die Hauptdomain bleibt auf `/wordpress/`. Alle drei Subdomains benötigen HTTPS und PHP 8.4 für die API.

## Vor dem ersten Deploy

1. Im World4You-Panel die API-Subdomain auf `/aesculapp-api/public/` umstellen und HTTPS sowie PHP 8.4 beibehalten. **Vor** dem Kopieren privater Dateien mit dem Marker `https://api.stadtapotheke-trofaiach.at/aesculapp-docroot.txt` prüfen, dass wirklich der Unterordner `public` ausgeliefert wird. WordPress nicht umstellen.
2. Eine **eigene** Datenbank samt Benutzer für die Testinstallation anlegen. In `web/aesculapp-api/.env.local` mindestens `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET`, `DATABASE_URL`, `APP_TENANT_SLUG=stadtapotheke-trofaiach`, `APP_CLIENT_URL=https://app.stadtapotheke-trofaiach.at`, `APP_ADMIN_URL=https://admin.stadtapotheke-trofaiach.at`, `DEFAULT_URI=https://api.stadtapotheke-trofaiach.at`, `CORS_ALLOW_ORIGIN='^https://(app|admin)\.stadtapotheke-trofaiach\.at$'`, `JWT_PASSPHRASE`, `MAILER_DSN`, `APP_MAIL_FROM`, `APP_CRON_SECRET` und `WEB_PUSH_VAPID_SUBJECT` setzen. Sonderzeichen im Datenbankkennwort in `DATABASE_URL` URL-kodieren. Keine Geheimnisse einchecken.
3. Unter `web/aesculapp-api/config/jwt` ein eigenes JWT-Schlüsselpaar hinterlegen und seine Passphrase in `.env.local` setzen. Für Chat und andere private Daten den **bestehenden** Schlüssel unter `web/aesculapp-api/var/private/private-data.key` übernehmen und sichern. Diesen Schlüssel nach Datenanlage nie unbedacht ersetzen. Ein neues VAPID-Schlüsselpaar für Web-Push wird ebenfalls separat benötigt.
4. In GitHub ein Environment `apotheke-test` anlegen. Dort die Secrets `APOTHEKE_SSH_KEY` (privater Deploy-Schlüssel) und `APOTHEKE_KNOWN_HOSTS` (verifizierter SSH-Hostkey-Eintrag für `www21.world4you.com`) setzen. Der öffentliche Deploy-Schlüssel muss im **World4You-Panel** als SSH-Schlüssel freigeschaltet werden: Ein direkter Eintrag in `~/.ssh/authorized_keys` wurde vom Hosting-SSH-Server nicht akzeptiert. Der separate Schlüssel liegt lokal unter `C:\Users\User\.ssh\aesculapp_apotheke_github_rsa` (privat) und `.pub` (öffentlich); den privaten Inhalt nicht im Chat teilen. Optional GitHub-Environment-Reviewer für eine manuelle Freigabe einrichten.
5. Erst nach Abschluss dieser Einrichtung `APOTHEKE_DEPLOY_ENABLED=true` als Repository-Variable oder als Variable des Environments `apotheke-test` setzen. Der erste Job-Schritt prüft den Wert nach dem Laden des Environments und bricht bei fehlendem Wert oder `false` mit einer klaren Fehlermeldung vor Build und Kopieren ab. Der Workflow bricht außerdem vor dem Kopieren ab, wenn private Konfiguration, Schlüssel oder der öffentlich erreichbare DocumentRoot-Marker fehlen.

Die Datenbank braucht vor einem sinnvollen App-Test außerdem einen Tenant und ein Admin-Konto. `app:seed:sta` ist dafür **nicht** geeignet: Der Befehl erzeugt bekannte Prototyp-Zugangsdaten. Nach dem ersten Deploy und den Migrationen wird daher im Verzeichnis `web/aesculapp-api` im interaktiven SSH-Terminal `APP_ENV=prod APP_DEBUG=0 php84 bin/console app:bootstrap:tenant-admin` ausgeführt. Der Befehl verlangt eine vollständig leere Installation, fragt Mandantenname, Admin-Benutzername, E-Mail-Adresse und Anzeigename ab und liest das mindestens 14 Zeichen lange Passwort verdeckt und zweimal ein. Er legt keine Demo-Kunden an. Passwort weder als Shell-Argument noch im Chat übermitteln.

Die privaten Serverdateien `.env.local`, `config/jwt/private.pem`, `config/jwt/public.pem` und `var/private/private-data.key` wurden zunächst unter `C:\Users\User\.ssh\aesculapp-apotheke-server-backup-20260923` lokal gesichert und per SHA-256 verglichen. Zusätzlich ist eine verschlüsselte Offline-Sicherung nötig; die lokale Kopie allein schützt nicht vor einem Ausfall dieses PCs.

Beim Wechsel vom zunächst außerhalb von `web` liegenden `aesculapp-server` wurde erst der neue `public`-Ordner samt DocumentRoot-Prüfmarker bereitgestellt und die Subdomain darauf umgestellt. **Erst nach erfolgreichem Abruf des Markers** wurden Symfony, `.env.local`, JWT-Schlüssel und der Schlüssel für private Daten in `web/aesculapp-api` kopiert und die sensiblen Dateien mit der Quelle verglichen. Der alte Ordner bleibt vorläufig als inaktive Rückfallkopie erhalten. Die früheren Uploads aus `web/aesculapp-api/uploads` wurden nach `web/aesculapp-api/public/uploads` kopiert; der alte Symlink wurde im neuen Projektordner durch einen echten Ordner ersetzt. Die alten Kopien nicht löschen, bevor Login, Medien und ein weiterer GitHub-Deploy geprüft sind.

Der Shared Host hat `php84` für die CLI; `php` allein ist dort PHP 7.2. Der Workflow baut Composer-Abhängigkeiten auf GitHub und führt Migrationen mit `php84` aus. SSH muss den MAC `hmac-sha2-256` verwenden, da der standardmäßig ausgehandelte MAC bei diesem Host Verbindungsfehler verursacht hat.

## Promotion und Deployment

Zuerst den gewünschten Stand wie gewohnt mit `./merge-dev-to-main.ps1` nach `main` bringen. Wenn die Apotheke diesen Stand übernehmen soll, im sauberen Arbeitsbaum `./merge-main-to-apotheke-test.ps1` ausführen. Vor jeglichem Fetch, Merge oder Push warnt das Skript vor dem möglichen Apotheken-Deploy und verlangt die exakte Terminaleingabe `yes`; jede andere Eingabe oder fehlende interaktive Eingabe bricht ab. Danach holt es beide Remote-Branches per Fast-Forward, merged `main` nach `apotheke-test` und pusht ausschließlich diesen Branch. Ein Push auf `main` aktualisiert **nicht** automatisch die Apothekeninstallation. Kein Force-Push und kein automatischer Merge-Abbruch.

Der Apotheken-Workflow baut Kunden-App, Admin und Symfony separat. Nur im Apotheken-Build werden Aufrufe der Demo-API auf `https://api.stadtapotheke-trofaiach.at/api/v1` umgestellt. Die Symfony-`public/index.php` ist der reguläre API-Einstieg. Uploads liegen dauerhaft unter `web/aesculapp-api/public/uploads` und benötigen keinen Symlink. Der Deploy schützt `.env.local`, `config/jwt`, `var` und Uploads vor dem Löschabgleich. Er prüft vor dem Kopieren den öffentlichen DocumentRoot-Marker, führt anschließend Migrationen und Cache-Clear aus und verlangt zum Schluss eine echte JSON-Antwort des API-Healthchecks.

Für einen neuen Installationsstand zuerst Datenbankbackup erstellen. Ein fehlgeschlagener Build verändert den Server nicht; ein Fehler nach Beginn des Kopierens kann hingegen eine teilweise aktualisierte Installation hinterlassen und muss anhand des GitHub-Logs geprüft werden. Der erste echte Deploy sollte deshalb begleitet und anschließend mit Login, API-Health, Admin, Medien-Upload und E-Mail getestet werden.
