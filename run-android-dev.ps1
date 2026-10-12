[CmdletBinding()]
param([string]$DeviceSerial)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath
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

try {
    $health = Invoke-RestMethod 'http://127.0.0.1:6080/api/health' -TimeoutSec 3
    if ($health.status -ne 'ok') { throw 'Ungültige API-Antwort.' }
} catch {
    throw 'Die lokale API ist nicht erreichbar. Bitte zuerst .\start-dev.ps1 ausführen.'
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

& $adb -s $DeviceSerial reverse tcp:6080 tcp:6080
if ($LASTEXITCODE -ne 0) { throw 'Die USB-Portweiterleitung zur API ist fehlgeschlagen.' }
& $adb -s $DeviceSerial reverse tcp:4200 tcp:4200
if ($LASTEXITCODE -ne 0) { throw 'Die USB-Portweiterleitung zur PWA-Fallback-Seite ist fehlgeschlagen.' }

Push-Location (Join-Path $projectRoot 'client')
try {
    & npm.cmd run android:run -- --target $DeviceSerial
    if ($LASTEXITCODE -ne 0) { throw 'Der Android-Build oder die Installation ist fehlgeschlagen.' }
} finally {
    Pop-Location
}
