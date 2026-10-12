# Android-Testverteilung

Die Online-App hat die Paketkennung `at.stadtapotheke.trofaiach`. Für die derzeitige Testphase ist `Floatbox` das Standard-API-Ziel. Die signierte APK wird so gebaut:

```powershell
.\build-android-apotheke-release.ps1 -ApiTarget Floatbox
```

Das Skript baut Angular, synchronisiert Capacitor, erstellt eine signierte Release-APK und prüft deren Signatur. Die Datei liegt anschließend unter `client/android-apotheke/app/build/outputs/apk/release/app-release.apk`. Für einen späteren Test gegen die Apotheken-API ist ausdrücklich `-ApiTarget Apotheke` anzugeben; der App-Link-Host wechselt dabei mit. Da beide Varianten dieselbe Paketkennung haben, nicht unbemerkt zwischen den Datenbeständen wechseln oder APKs an Tester verteilen, ohne das Ziel zu nennen.

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
