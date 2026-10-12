[CmdletBinding()]
param(
    [string]$DeviceSerial,
    [ValidateSet('Floatbox', 'Apotheke')]
    [string]$ApiTarget = 'Floatbox',
    [switch]$BuildOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath
$clientRoot = Join-Path $projectRoot 'client'
$androidRoot = Join-Path $clientRoot 'android-apotheke'
$apiUrls = @{
    Floatbox = 'https://api.aesculapp.floatbox.at/api/v1'
    Apotheke = 'https://api.stadtapotheke-trofaiach.at/api/v1'
}
$apiBaseUrl = $apiUrls[$ApiTarget]
$healthUrl = $apiBaseUrl -replace '/api/v1$', '/api/health'
$sdkHome = [Environment]::GetEnvironmentVariable('ANDROID_HOME', 'User')
if (-not $sdkHome) { $sdkHome = $env:ANDROID_HOME }
$javaHome = [Environment]::GetEnvironmentVariable('JAVA_HOME', 'Machine')
if (-not $javaHome) { $javaHome = $env:JAVA_HOME }

$adb = if ($sdkHome) { Join-Path $sdkHome 'platform-tools\adb.exe' } else { $null }
if (-not $adb -or -not (Test-Path -LiteralPath $adb)) {
    throw 'Android SDK/adb fehlt. Bitte ANDROID_HOME auf das installierte Android SDK setzen.'
}
if (-not $javaHome -or -not (Test-Path -LiteralPath (Join-Path $javaHome 'bin\javac.exe'))) {
    throw 'JDK 21 fehlt. Bitte JAVA_HOME auf eine JDK-21-Installation setzen.'
}
$env:ANDROID_HOME = $sdkHome
$env:JAVA_HOME = $javaHome

if (-not $BuildOnly) {
    try {
        $health = Invoke-RestMethod $healthUrl -TimeoutSec 10
        if ($health.status -ne 'ok') { throw 'Ungültige API-Antwort.' }
    } catch {
        throw "Die $ApiTarget-API ist nicht über HTTPS erreichbar."
    }

    try {
        $preflight = Invoke-WebRequest "$apiBaseUrl/auth/refresh" -Method Options -Headers @{
            Origin = 'https://localhost'
            'Access-Control-Request-Method' = 'POST'
            'Access-Control-Request-Headers' = 'content-type'
        } -TimeoutSec 10
    } catch {
        throw "Die $ApiTarget-API beantwortet die native CORS-Vorprüfung nicht."
    }
    if ($preflight.Headers['Access-Control-Allow-Origin'] -ne 'https://localhost') {
        throw "Die $ApiTarget-API erlaubt https://localhost noch nicht. Native Anmeldung wäre nicht möglich."
    }

    $connectedDevices = @(& $adb devices | Select-String '^([^\s]+)\s+device$' | ForEach-Object { $_.Matches[0].Groups[1].Value })
    if (-not $DeviceSerial) {
        if ($connectedDevices.Count -ne 1) {
            throw 'Bitte genau ein freigegebenes Android-Gerät verbinden oder -DeviceSerial angeben.'
        }
        $DeviceSerial = $connectedDevices[0]
    } elseif ($DeviceSerial -notin $connectedDevices) {
        throw "Android-Gerät $DeviceSerial ist nicht für USB-Debugging freigegeben."
    }
}

if (-not (Test-Path -LiteralPath (Join-Path $androidRoot 'app\google-services.json'))) {
    Write-Warning 'Die apothekeneigene google-services.json fehlt. Diese Installation kann noch keine nativen Push-Nachrichten empfangen.'
}

$oldTarget = $env:AESCULAPP_NATIVE_TARGET
$oldLinkHost = $env:ORG_GRADLE_PROJECT_aesculappLinkHost
$env:AESCULAPP_NATIVE_TARGET = 'apotheke'
$env:ORG_GRADLE_PROJECT_aesculappLinkHost = if ($ApiTarget -eq 'Floatbox') { 'aesculapp.floatbox.at' } else { 'app.stadtapotheke-trofaiach.at' }
Push-Location $clientRoot
try {
    Write-Host "Online-App: at.stadtapotheke.trofaiach; API-Ziel: $apiBaseUrl"
    if ($ApiTarget -eq 'Floatbox') {
        & npm.cmd run build:floatbox-native
    } else {
        & npm.cmd run build:apotheke-native
    }
    if ($LASTEXITCODE -ne 0) { throw "Der $ApiTarget-Web-Build ist fehlgeschlagen." }

    $builtScripts = Get-ChildItem -LiteralPath 'dist\aesculapp\browser' -Filter '*.js' -File
    $expectedApiFound = $null -ne ($builtScripts | Select-String -SimpleMatch $apiBaseUrl -Quiet | Where-Object { $_ } | Select-Object -First 1)
    $otherApiUrl = if ($ApiTarget -eq 'Floatbox') { $apiUrls.Apotheke } else { $apiUrls.Floatbox }
    $otherApiFound = $null -ne ($builtScripts | Select-String -SimpleMatch $otherApiUrl -Quiet | Where-Object { $_ } | Select-Object -First 1)
    $localApiFound = $null -ne ($builtScripts | Select-String -SimpleMatch 'http://localhost:6080/api/v1' -Quiet | Where-Object { $_ } | Select-Object -First 1)
    if (-not $expectedApiFound -or $otherApiFound -or $localApiFound) {
        throw "API-Konfiguration für $ApiTarget ist nicht eindeutig. Installation abgebrochen."
    }

    & '.\node_modules\.bin\cap.cmd' sync android
    if ($LASTEXITCODE -ne 0) { throw 'Capacitor-Synchronisierung für die Apotheken-App ist fehlgeschlagen.' }

    if ($BuildOnly) {
        Push-Location $androidRoot
        try {
            & '.\gradlew.bat' assembleDebug
            if ($LASTEXITCODE -ne 0) { throw 'Der Apotheken-APK-Build ist fehlgeschlagen.' }
            Write-Host 'Apotheken-APK: client\android-apotheke\app\build\outputs\apk\debug\app-debug.apk'
        } finally {
            Pop-Location
        }
    } else {
        & '.\node_modules\.bin\cap.cmd' run android --no-sync --target $DeviceSerial
        if ($LASTEXITCODE -ne 0) { throw 'Der Android-Build oder die Installation ist fehlgeschlagen.' }
    }
} finally {
    Pop-Location
    $env:AESCULAPP_NATIVE_TARGET = $oldTarget
    $env:ORG_GRADLE_PROJECT_aesculappLinkHost = $oldLinkHost
}
