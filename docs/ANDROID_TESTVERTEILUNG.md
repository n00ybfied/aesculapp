# Android-Testverteilung

Die Online-App hat die Paketkennung `at.stadtapotheke.trofaiach`. Ab Version `0.1.1` (`versionCode` 2) ist die Apotheken-API das Standardziel. Die signierte APK wird so gebaut:

```powershell
.\build-android-apotheke-release.ps1
```

Das Skript baut Angular, synchronisiert Capacitor, erstellt eine signierte Release-APK und prüft deren Signatur. Die Datei liegt anschließend unter `client/android-apotheke/app/build/outputs/apk/release/app-release.apk`. Für einen bewussten Test gegen die Floatbox-Demo-API ist ausdrücklich `-ApiTarget Floatbox` anzugeben; der App-Link-Host wechselt dabei mit. Da beide Varianten dieselbe Paketkennung haben, nicht unbemerkt zwischen den Datenbeständen wechseln oder APKs an Tester verteilen, ohne das Ziel zu nennen.

Die signierte Apotheken-Test-APK `0.1.1` liegt auf der eigenen Download-Subdomain `https://android-apk.stadtapotheke-trofaiach.at/`. Die Installationsseite wird aus `ops/world4you/android-apk/index.html` bereitgestellt; die APK ist unter `https://android-apk.stadtapotheke-trofaiach.at/stadtapotheke-trofaiach-android-0.1.1.apk` direkt erreichbar. Der frühere Download unter der Kunden-App-Subdomain bleibt vorerst als Rückfalllink bestehen. Dieser Ordner ist von WordPress, Admin und API getrennt. Vor jeder Verteilung eines neuen Builds die Versionsnummer erhöhen, erneut signieren, mit eigenem Dateinamen hochladen und lokale sowie entfernte SHA-256-Prüfsumme vergleichen. Ein APK-Upload aktualisiert weder die Kunden-PWA noch die Symfony-API.

Die APK enthält die neuen Gewinnspiel-Funktionen. Sie benötigen denselben Stand der Symfony-API auf dem Apotheken-Testserver; deshalb muss der `apotheke-test`-Deploy erfolgreich durchlaufen, bevor diese Seiten auf dem Gerät getestet werden. Die native FCM-Konfiguration (`NATIVE_PUSH_ANDROID_APP_ID` und `FCM_SERVICE_ACCOUNT_FILE`) fehlt dort noch; native Pushs sind daher vorerst nicht einsatzbereit. Vor breiterer Verteilung Anmeldung, Sitzungs-Refresh, Push und neue Seiten auf einem echten Gerät prüfen. Das Hochladen der APK ersetzt diese Schritte nicht.

## Signierschlüssel sichern

Der einmalig erzeugte Schlüssel liegt lokal unter `.runtime/android-signing/apotheke-upload.jks`. Das Kennwort liegt daneben als `apotheke-password.dpapi` und kann **nur vom aktuellen Windows-Benutzer auf diesem PC** entschlüsselt werden. Beide Dateien sind von Git ausgeschlossen. Der Schlüssel darf nicht neu erzeugt werden, wenn eine verteilte App später aktualisiert werden soll.

Vor der ersten dauerhaften Verteilung die JKS-Datei an einem sicheren Ort außerhalb dieses PCs sichern und das Kennwort getrennt in einem Passwortmanager hinterlegen. Das Kennwort kann der angemeldete Windows-Benutzer in PowerShell lokal anzeigen:

```powershell
Get-Content .runtime/android-signing/apotheke-password.dpapi |
  ConvertTo-SecureString |
  ConvertFrom-SecureString -AsPlainText
```

Das Kennwort nicht in Git, in diesen Chat, in Tickets oder in E-Mails kopieren. Eine Kopie der DPAPI-Datei allein ist **kein** plattformunabhängiges Backup. Wenn die App später über Google Play mit Play App Signing verteilt wird, muss für App-Links der Fingerabdruck des Play-App-Signing-Zertifikats ergänzt werden; der lokale Upload-Schlüssel kann sich davon unterscheiden.

## Installation und Links

Auf dem P30 ist zurzeit eine Debug-signierte Installation derselben Paketkennung vorhanden. Die Release-APK hat eine andere Signatur und kann sie nicht als Update ersetzen. Eine Deinstallation würde deren lokale App-Daten löschen; deshalb erfolgt sie nicht automatisch. Für erste Linktests lässt `client/public/.well-known/assetlinks.json` sowohl das aktuelle Debug- als auch das Release-Zertifikat zu. Nach dem nächsten Floatbox-PWA-Deploy muss `https://aesculapp.floatbox.at/.well-known/assetlinks.json` JSON ohne Weiterleitung oder HTML-Fallback liefern. Erst dann kann Android die Domain-Verknüpfung verifizieren. Die eigentlichen E-Mail-Links bleiben auch ohne App im Browser funktionsfähig.
