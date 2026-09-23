# Apotheken-Testinstallation auf World4You

Diese Installation ist von der öffentlichen Demo und von WordPress getrennt. Der Branch `main` bedient weiterhin die Demo; `apotheke-test` ist der bewusst und nur gelegentlich aktualisierte Stand für die Apotheke.

## Verzeichnisse und Subdomains

Die folgenden Verzeichnisse sind auf `www21.world4you.com` unter `/home/.sites/95/site1646665` angelegt:

| Subdomain | Zielverzeichnis im World4You-Panel | Inhalt |
| --- | --- | --- |
| `app.stadtapotheke-trofaiach.at` | `/aesculapp-client/` | Kunden-App |
| `admin.stadtapotheke-trofaiach.at` | `/aesculapp-admin/` | Admin-App |
| `api.stadtapotheke-trofaiach.at` | `/aesculapp-api/` | Ausschließlich öffentlicher API-Einstieg und Uploads |

Die private Symfony-Anwendung liegt daneben unter `/home/.sites/95/site1646665/aesculapp-server`, also **nicht** im Verzeichnis `web`. Die Hauptdomain bleibt auf `/wordpress/`. Alle drei Subdomains benötigen HTTPS und PHP 8.4 für die API.

## Vor dem ersten Deploy

1. Im World4You-Panel die drei Subdomains mit den oben genannten Startpunkten anlegen und HTTPS aktivieren. Prüfen, dass der API-Startpunkt wirklich `/aesculapp-api/` ist. WordPress nicht umstellen.
2. Eine **eigene** Datenbank samt Benutzer für die Testinstallation anlegen. In `aesculapp-server/.env.local` mindestens `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET`, `DATABASE_URL`, `APP_TENANT_SLUG=stadtapotheke-trofaiach`, `APP_CLIENT_URL=https://app.stadtapotheke-trofaiach.at`, `APP_ADMIN_URL=https://admin.stadtapotheke-trofaiach.at`, `DEFAULT_URI=https://api.stadtapotheke-trofaiach.at`, `CORS_ALLOW_ORIGIN='^https://(app|admin)\.stadtapotheke-trofaiach\.at$'`, `JWT_PASSPHRASE`, `MAILER_DSN`, `APP_MAIL_FROM`, `APP_CRON_SECRET` und `WEB_PUSH_VAPID_SUBJECT` setzen. Sonderzeichen im Datenbankkennwort in `DATABASE_URL` URL-kodieren. Keine Geheimnisse einchecken.
3. Unter `aesculapp-server/config/jwt` ein eigenes JWT-Schlüsselpaar hinterlegen und seine Passphrase in `.env.local` setzen. Für Chat und andere private Daten einen eigenen Schlüssel unter `aesculapp-server/var/private/private-data.key` erstellen und sichern. Diesen Schlüssel nach Datenanlage nie unbedacht ersetzen. Ein neues VAPID-Schlüsselpaar für Web-Push wird ebenfalls separat benötigt.
4. In GitHub ein Environment `apotheke-test` anlegen. Dort die Secrets `APOTHEKE_SSH_KEY` (privater Deploy-Schlüssel) und `APOTHEKE_KNOWN_HOSTS` (verifizierter SSH-Hostkey-Eintrag für `www21.world4you.com`) setzen. Der öffentliche Deploy-Schlüssel muss auf dem World4You-Account in `authorized_keys` stehen. Ein eigener Deploy-Schlüssel ist gegenüber dem persönlichen SSH-Schlüssel vorzuziehen. Optional GitHub-Environment-Reviewer für eine manuelle Freigabe einrichten.
5. Erst nach Abschluss dieser Einrichtung die **Repository-Variable** `APOTHEKE_DEPLOY_ENABLED=true` setzen. Vorher wird der Workflow selbst bei einem Push übersprungen. Der Workflow bricht vor dem Kopieren ab, wenn `.env.local` oder das JWT-Verzeichnis fehlen.

Die Datenbank braucht vor einem sinnvollen App-Test außerdem einen Tenant und ein Admin-Konto. `app:seed:sta` ist dafür **nicht** geeignet: Der Befehl erzeugt bekannte Prototyp-Zugangsdaten. Für die interne Installation ist ein separater sicherer Bootstrap-Weg beziehungsweise eine kontrollierte Erst-Anlage mit individuellen Kennwörtern erforderlich. Bis dahin bleibt die Deployment-Freigabe deaktiviert.

Der Shared Host hat `php84` für die CLI; `php` allein ist dort PHP 7.2. Der Workflow baut Composer-Abhängigkeiten auf GitHub und führt Migrationen mit `php84` aus. SSH muss den MAC `hmac-sha2-256` verwenden, da der standardmäßig ausgehandelte MAC bei diesem Host Verbindungsfehler verursacht hat.

## Promotion und Deployment

Zuerst den gewünschten Stand wie gewohnt mit `./merge-dev-to-main.ps1` nach `main` bringen. Wenn die Apotheke diesen Stand übernehmen soll, im sauberen Arbeitsbaum `./merge-main-to-apotheke-test.ps1` ausführen. Das Skript holt beide Remote-Branches per Fast-Forward, merged `main` nach `apotheke-test` und pusht ausschließlich diesen Branch. Ein Push auf `main` aktualisiert **nicht** automatisch die Apothekeninstallation. Kein Force-Push und kein automatischer Merge-Abbruch.

Der Apotheken-Workflow baut Kunden-App, Admin und Symfony separat. Nur im Apotheken-Build werden Aufrufe der Demo-API auf `https://api.stadtapotheke-trofaiach.at/api/v1` umgestellt. Das öffentliche API-`index.php` lädt den Symfony-Runtime-Autoloader aus dem privaten Serverordner. Die Uploads liegen dauerhaft unter `web/aesculapp-api/uploads`; Symfony sieht sie über einen Symlink in seinem `public/uploads`. Der Deploy schützt `.env.local`, `config/jwt`, `var` und Uploads vor dem Löschabgleich. Nach dem Kopieren werden Migrationen und Cache-Clear ausgeführt.

Für einen neuen Installationsstand zuerst Datenbankbackup erstellen. Ein fehlgeschlagener Build verändert den Server nicht; ein Fehler nach Beginn des Kopierens kann hingegen eine teilweise aktualisierte Installation hinterlassen und muss anhand des GitHub-Logs geprüft werden. Der erste echte Deploy sollte deshalb begleitet und anschließend mit Login, API-Health, Admin, Medien-Upload und E-Mail getestet werden.
