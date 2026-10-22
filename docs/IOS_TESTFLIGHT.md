# iOS-TestFlight-Build

Der manuelle Workflow `.github/workflows/ios-testflight.yml` baut die temporäre iOS-Test-App `at.floatbox.aesculapp.sta.test` für die Apotheken-API. Er verändert weder die PWA-Deploys noch Android. Standardmäßig wird nur eine signierte IPA als GitHub-Actions-Artefakt erzeugt. Erst die beim Start gewählte Option „Signierte IPA nach App Store Connect hochladen“ überträgt den Build zu Apple.

## Einmalig: GitHub-Secrets

Unter **Repository → Settings → Secrets and variables → Actions → Repository secrets** hinterlegen:

| Secret | Inhalt |
| --- | --- |
| `IOS_DISTRIBUTION_P12_BASE64` | Base64 der lokalen `.runtime/ios-signing/testflight-distribution.p12` |
| `IOS_DISTRIBUTION_P12_PASSWORD` | Passwort dieser P12-Datei |
| `IOS_TESTFLIGHT_PROFILE_BASE64` | Base64 der lokalen `.runtime/ios-signing/AesculApp_TestFlight.mobileprovision` |

Für den optionalen Upload zusätzlich einen App-Store-Connect-API-Schlüssel mit geeigneter Berechtigung erstellen und diese drei Secrets hinterlegen:

| Secret | Inhalt |
| --- | --- |
| `APP_STORE_CONNECT_KEY_ID` | Key ID des API-Schlüssels |
| `APP_STORE_CONNECT_ISSUER_ID` | Issuer ID des API-Schlüssels |
| `APP_STORE_CONNECT_P8_BASE64` | Base64 der einmalig herunterladbaren `.p8`-Datei |

Die binären Dateien können in PowerShell ohne Konsolenausgabe nacheinander in die Zwischenablage kopiert werden; nach jedem Befehl den Inhalt direkt in das jeweilige GitHub-Secret einfügen:

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes('G:\aesculapp\.runtime\ios-signing\testflight-distribution.p12')) | Set-Clipboard
[Convert]::ToBase64String([IO.File]::ReadAllBytes('G:\aesculapp\.runtime\ios-signing\AesculApp_TestFlight.mobileprovision')) | Set-Clipboard
```

Für die `.p8`-Datei denselben Befehl mit ihrem tatsächlichen Pfad verwenden. Danach die Zwischenablage mit einem unverfänglichen Wert überschreiben. Weder P12, Profil, `.p8` noch Passwörter in Git, Issues oder Chat hochladen. P12 und Kennwort unabhängig vom Arbeits-PC sichern.

## Starten

Unter **Actions → iOS TestFlight build → Run workflow** den gewünschten Branch auswählen. Für den ersten Durchlauf die Upload-Option deaktiviert lassen. Nach erfolgreichem Build die IPA unter **Artifacts** prüfen; der Workflow bewahrt sie sieben Tage auf. Erst danach bei einem neuen manuellen Lauf die Upload-Option aktivieren. App Store Connect verarbeitet einen erfolgreichen Upload asynchron; erst nach dieser Verarbeitung wird er unter TestFlight sichtbar. Der iOS-Buildzähler verwendet die GitHub-Run-Nummer.

Vor einem iPhone-Funktionstest muss die Apotheken-API `APP_NATIVE_IOS_ORIGIN=capacitor://localhost` und den entsprechenden CORS-Ursprung unterstützen.

## Kundenlinks in der iOS-Test-App

Für E-Mail-Links muss in Apple Developer unter **Certificates, Identifiers & Profiles → Identifiers → `at.floatbox.aesculapp.sta.test`** die Capability **Associated Domains** aktiviert werden. Danach ein neues App-Store-Connect-Provisioning-Profile für dieselbe App-ID erstellen beziehungsweise das bestehende neu generieren. Die neue `.mobileprovision` lokal sicher ablegen und `IOS_TESTFLIGHT_PROFILE_BASE64` mit deren Base64-Inhalt aktualisieren; Zertifikat, P12 und API-Schlüssel bleiben unverändert. Der Workflow prüft die Associated Domain im Profil vor dem Build.

Die Kunden-PWA muss `/.well-known/apple-app-site-association` auf `app.stadtapotheke-trofaiach.at` direkt mit Status 200 und JSON-Inhalt ausliefern. Die Datei liegt unter `client/public/.well-known/` und wird erst mit dem Apotheken-PWA-Deploy öffentlich. Die iOS-App verwendet `applinks:app.stadtapotheke-trofaiach.at`; die bestehende TestFlight-Version 0.1.0 (3) enthält diese Entitlement noch nicht. Nach Capability, Profil und PWA-Deploy ist daher ein **neuer TestFlight-Build** nötig. Die App verarbeitet nur die Kundenrouten aus `docs/KUNDENLINKS.md`; ohne native Installation bleiben dieselben URLs im Browser verfügbar. Native iOS-Pushs sind noch nicht implementiert.
