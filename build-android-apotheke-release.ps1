[CmdletBinding()]
param(
    [ValidateSet('Floatbox', 'Apotheke')]
    [string]$ApiTarget = 'Apotheke',
    [switch]$InitializeSigningKey
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath
$signingRoot = Join-Path $projectRoot '.runtime\android-signing'
$keyFile = Join-Path $signingRoot 'apotheke-upload.jks'
$passwordFile = Join-Path $signingRoot 'apotheke-password.dpapi'
$javaHome = [Environment]::GetEnvironmentVariable('JAVA_HOME', 'Machine')
if (-not $javaHome) { $javaHome = $env:JAVA_HOME }
$keytool = if ($javaHome) { Join-Path $javaHome 'bin\keytool.exe' } else { $null }
if (-not $keytool -or -not (Test-Path -LiteralPath $keytool)) {
    throw 'JDK/keytool fehlt. Bitte JAVA_HOME auf die JDK-21-Installation setzen.'
}

if ($InitializeSigningKey) {
    if ((Test-Path -LiteralPath $keyFile) -or (Test-Path -LiteralPath $passwordFile)) {
        throw 'Signierschlüssel oder Kennwortdatei existiert bereits. Es wird niemals automatisch ein Ersatzschlüssel erzeugt.'
    }

    New-Item -ItemType Directory -Path $signingRoot -Force | Out-Null
    $password = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(48))
    $env:AESCULAPP_KEYTOOL_PASSWORD = $password
    try {
        & $keytool -genkeypair -v -keystore $keyFile -storetype PKCS12 `
            -alias aesculapp_apotheke -keyalg RSA -keysize 3072 -validity 10000 `
            -dname 'CN=Stadtapotheke Trofaiach, O=Stadtapotheke Trofaiach, C=AT' `
            -storepass:env AESCULAPP_KEYTOOL_PASSWORD -keypass:env AESCULAPP_KEYTOOL_PASSWORD
        if ($LASTEXITCODE -ne 0) { throw 'Der Signierschlüssel konnte nicht erzeugt werden.' }
        $password | ConvertTo-SecureString -AsPlainText -Force | ConvertFrom-SecureString |
            Set-Content -LiteralPath $passwordFile -NoNewline
    } finally {
        Remove-Item Env:\AESCULAPP_KEYTOOL_PASSWORD -ErrorAction SilentlyContinue
        $password = $null
    }
    Write-Host "Signierschlüssel angelegt: $keyFile"
    Write-Warning 'Schlüsseldatei und Kennwort müssen vor einer dauerhaften Verteilung unabhängig vom PC gesichert werden.'
}

if (-not (Test-Path -LiteralPath $keyFile) -or -not (Test-Path -LiteralPath $passwordFile)) {
    throw 'Signierschlüssel fehlt. Einmalig mit -InitializeSigningKey erzeugen.'
}

$encryptedPassword = Get-Content -LiteralPath $passwordFile -Raw
$securePassword = ConvertTo-SecureString $encryptedPassword
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}

$oldStoreFile = $env:ORG_GRADLE_PROJECT_aesculappSigningStoreFile
$oldStorePassword = $env:ORG_GRADLE_PROJECT_aesculappSigningStorePassword
$oldKeyPassword = $env:ORG_GRADLE_PROJECT_aesculappSigningKeyPassword
$oldLinkHost = $env:ORG_GRADLE_PROJECT_aesculappLinkHost
$env:ORG_GRADLE_PROJECT_aesculappSigningStoreFile = $keyFile
$env:ORG_GRADLE_PROJECT_aesculappSigningStorePassword = $plainPassword
$env:ORG_GRADLE_PROJECT_aesculappSigningKeyPassword = $plainPassword
$env:ORG_GRADLE_PROJECT_aesculappLinkHost = if ($ApiTarget -eq 'Floatbox') { 'aesculapp.floatbox.at' } else { 'app.stadtapotheke-trofaiach.at' }
$plainPassword = $null

try {
    & (Join-Path $projectRoot 'run-android-apotheke.ps1') -ApiTarget $ApiTarget -BuildOnly
    if ($LASTEXITCODE -ne 0) { throw 'Der native Testbuild ist fehlgeschlagen.' }

    Push-Location (Join-Path $projectRoot 'client\android-apotheke')
    try {
        & '.\gradlew.bat' --no-daemon assembleRelease
        if ($LASTEXITCODE -ne 0) { throw 'Der signierte Release-Build ist fehlgeschlagen.' }
    } finally {
        Pop-Location
    }

    $sdkHome = [Environment]::GetEnvironmentVariable('ANDROID_HOME', 'User')
    if (-not $sdkHome) { $sdkHome = $env:ANDROID_HOME }
    $apk = Join-Path $projectRoot 'client\android-apotheke\app\build\outputs\apk\release\app-release.apk'
    $apksigner = if ($sdkHome) {
        Get-ChildItem (Join-Path $sdkHome 'build-tools') -Directory |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName 'apksigner.bat' } |
            Where-Object { Test-Path -LiteralPath $_ } |
            Select-Object -First 1
    }
    if (-not $apksigner -or -not (Test-Path -LiteralPath $apk)) {
        throw 'Signaturprüfung nicht möglich: apksigner oder APK fehlt.'
    }
    & $apksigner verify --verbose --print-certs $apk
    if ($LASTEXITCODE -ne 0) { throw 'Die APK-Signatur ist ungültig.' }
    Write-Host "Signierte Test-APK: $apk"
} finally {
    $env:ORG_GRADLE_PROJECT_aesculappSigningStoreFile = $oldStoreFile
    $env:ORG_GRADLE_PROJECT_aesculappSigningStorePassword = $oldStorePassword
    $env:ORG_GRADLE_PROJECT_aesculappSigningKeyPassword = $oldKeyPassword
    $env:ORG_GRADLE_PROJECT_aesculappLinkHost = $oldLinkHost
}
