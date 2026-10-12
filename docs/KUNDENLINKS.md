# Kundenlinks für PWA und native App

Kunden-E-Mails verwenden die kanonische URL aus `APP_CLIENT_URL`; Push-Nachrichten verwenden relative Pfade derselben Kunden-App. Ohne native Installation bleiben die Links im Browser erreichbar. Die native App übernimmt nur Links ihres zugeordneten Ursprungs und der unten aufgeführten Routen. Die Zuordnung liegt in `client/src/app/core/deep-links/native-link.service.ts` und wird dort getestet. Das Adminportal (`APP_ADMIN_URL`) ist ausdrücklich kein Ziel der Kunden-App.

| Anlass | Route der Kunden-App | Parameter | Anmeldung nötig? |
| --- | --- | --- | --- |
| Passwort zurücksetzen | `/passwort-zuruecksetzen` | `token`: 64 Hex-Zeichen | nein |
| E-Mail-Adresse bestätigen | `/e-mail-bestaetigen` | `token`: 64 Hex-Zeichen | nein |
| Neue E-Mail-Adresse bestätigen | `/e-mail-aendern` | `token`: 64 Hex-Zeichen | nein |
| Freund einladen | `/registrierung` | `ref`: 32 Hex-Zeichen | nein; für neue Konten |
| Familieneinladung annehmen | `/familie` | `token`: 64 Hex-Zeichen | ja |
| Familienzugang, Punkteteilung | `/familie` | keiner | ja |
| Terminbestätigung/-erinnerung | `/termine/meine` | keiner | ja |
| Chat-Antwort | `/chat` | keiner | ja |
| Apotheken-News | `/news/:id` | positive numerische ID | ja |
| Erfolgreiche Freundeseinladung | `/freunde-einladen` | keiner | ja |
| Push-Test | `/profil/bearbeiten` | keiner | ja |

Geschützte Routen behalten das Ziel über Login und gegebenenfalls die Ersteinrichtung als `returnUrl`. Die Familienseite verarbeitet einen neuen Einladungstoken auch dann, wenn sie schon geöffnet ist; die Registrierung übernimmt einen nachträglich geänderten Empfehlungscode. Unbekannte Pfade, fremde Hosts und unerwartete Parameter werden in der nativen App nicht übernommen.

Der lokale Android-Entwicklungsbuild akzeptiert dafür nur `http://localhost:4200` mit `adb reverse`. Das ist kein veröffentlichter App Link. Die Online-Android-App verwendet zurzeit die Floatbox-API und übernimmt deshalb ausschließlich Links von `https://aesculapp.floatbox.at`. Mit `-ApiTarget Apotheke` wechselt auch die erlaubte Kunden-Domain zu `https://app.stadtapotheke-trofaiach.at`. Das Android-Manifest enthält nur den zum Build gehörenden Host; `assetlinks.json` muss auf genau dieser Domain öffentlich unter `/.well-known/assetlinks.json` erreichbar sein und den Fingerabdruck der installierten APK enthalten. Ohne verifizierte Zuordnung bleiben die Links im Browser nutzbar. Für iOS fehlen weiterhin Associated Domains und die Apple-App-Site-Association-Datei. PWA und Adminportal bleiben unabhängig.
