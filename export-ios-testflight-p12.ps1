param(
    [string]$LaragonRoot = ''
)

$ErrorActionPreference = 'Stop'

$signingDirectory = Join-Path $PSScriptRoot '.runtime\ios-signing'
$certificatePath = Join-Path $signingDirectory 'distribution.cer'
$privateKeyPath = Join-Path $signingDirectory 'testflight-distribution.key.pem'
$outputPath = Join-Path $signingDirectory 'testflight-distribution.p12'

foreach ($requiredPath in @($certificatePath, $privateKeyPath)) {
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Signierdatei fehlt: $requiredPath"
    }
}
$packageAlreadyExists = Test-Path -LiteralPath $outputPath -PathType Leaf

if ($LaragonRoot -eq '') {
    $LaragonRoot = if ($env:LARAGON_ROOT) {
        $env:LARAGON_ROOT
    } else {
        Join-Path (Split-Path -Path $PSScriptRoot -Qualifier) 'laragon'
    }
}
$opensslPath = Join-Path $LaragonRoot 'bin\git\usr\bin\openssl.exe'
if (-not (Test-Path -LiteralPath $opensslPath -PathType Leaf)) {
    throw "OpenSSL wurde nicht gefunden: $opensslPath. Bei Bedarf -LaragonRoot angeben."
}

function ConvertFrom-SecureStringForOpenSsl {
    param([System.Security.SecureString]$SecureValue)

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($SecureValue)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

if ($packageAlreadyExists) {
    $secureExistingPassphrase = Read-Host 'P12-Datei existiert bereits. Gib ihr Passwort zur Prüfung ein (sie wird nicht überschrieben)' -AsSecureString
    $env:AESCULAPP_IOS_P12_PASSPHRASE = ConvertFrom-SecureStringForOpenSsl $secureExistingPassphrase
    $secureExistingPassphrase.Dispose()
    try {
        & $opensslPath pkcs12 -in $outputPath -noout -passin env:AESCULAPP_IOS_P12_PASSPHRASE 2>$null
        if ($LASTEXITCODE -ne 0) {
            throw 'Die bestehende P12-Datei konnte mit diesem Passwort nicht geprüft werden. Sie bleibt unverändert.'
        }
    } finally {
        Remove-Item Env:\AESCULAPP_IOS_P12_PASSPHRASE -ErrorAction SilentlyContinue
    }
    Write-Host "Bestehendes PKCS#12-Paket erfolgreich geprüft: $outputPath"
    return
}

$secureKeyPassphrase = Read-Host 'Passwort des vorhandenen privaten Schlüssels' -AsSecureString
$secureExportPassphrase = Read-Host 'Neues Passwort für die P12-Datei (mindestens 12 Zeichen)' -AsSecureString
if ($secureExportPassphrase.Length -lt 12) {
    throw 'Das neue P12-Passwort muss mindestens 12 Zeichen lang sein. Es wurde noch nichts exportiert.'
}
$secureConfirmation = Read-Host 'Neues P12-Passwort wiederholen' -AsSecureString
$keyPassphrase = ConvertFrom-SecureStringForOpenSsl $secureKeyPassphrase
$exportPassphrase = ConvertFrom-SecureStringForOpenSsl $secureExportPassphrase
$confirmation = ConvertFrom-SecureStringForOpenSsl $secureConfirmation
if ($exportPassphrase -cne $confirmation) {
    throw 'Die P12-Passwörter stimmen nicht überein. Es wurde noch nichts exportiert.'
}
$confirmation = $null
$secureKeyPassphrase.Dispose()
$secureExportPassphrase.Dispose()
$secureConfirmation.Dispose()

$env:AESCULAPP_IOS_PRIVATE_KEY_PASSPHRASE = $keyPassphrase
$env:AESCULAPP_IOS_P12_PASSPHRASE = $exportPassphrase
$keyPassphrase = $null
$exportPassphrase = $null
$temporaryCertificatePath = Join-Path $signingDirectory "$([guid]::NewGuid().ToString('N')).pem"

try {
    $certificatePublicKey = (& $opensslPath x509 -inform DER -in $certificatePath -pubkey -noout) -join "`n"
    if ($LASTEXITCODE -ne 0) {
        throw 'Das Apple-Zertifikat konnte nicht gelesen werden.'
    }
    $privatePublicKey = (& $opensslPath pkey -in $privateKeyPath -passin env:AESCULAPP_IOS_PRIVATE_KEY_PASSPHRASE -pubout) -join "`n"
    if ($LASTEXITCODE -ne 0 -or $privatePublicKey -ne $certificatePublicKey) {
        throw 'Das Schlüsselpasswort ist falsch oder der private Schlüssel passt nicht zum Zertifikat.'
    }

    & $opensslPath x509 -inform DER -in $certificatePath -out $temporaryCertificatePath
    if ($LASTEXITCODE -ne 0) {
        throw 'Das Apple-Zertifikat konnte nicht für den Export vorbereitet werden.'
    }
    & $opensslPath pkcs12 -export -inkey $privateKeyPath -in $temporaryCertificatePath -out $outputPath -name 'AesculApp TestFlight Distribution' -passin env:AESCULAPP_IOS_PRIVATE_KEY_PASSPHRASE -passout env:AESCULAPP_IOS_P12_PASSPHRASE
    if ($LASTEXITCODE -ne 0) {
        throw 'Das PKCS#12-Paket konnte nicht erstellt werden.'
    }
    & $opensslPath pkcs12 -in $outputPath -noout -passin env:AESCULAPP_IOS_P12_PASSPHRASE 2>$null
    if ($LASTEXITCODE -ne 0) {
        throw 'Das PKCS#12-Paket konnte nicht verifiziert werden.'
    }
} finally {
    Remove-Item Env:\AESCULAPP_IOS_PRIVATE_KEY_PASSPHRASE -ErrorAction SilentlyContinue
    Remove-Item Env:\AESCULAPP_IOS_P12_PASSPHRASE -ErrorAction SilentlyContinue
    if (Test-Path -LiteralPath $temporaryCertificatePath -PathType Leaf) {
        Remove-Item -LiteralPath $temporaryCertificatePath
    }
    if ((Test-Path -LiteralPath $outputPath -PathType Leaf) -and (Get-Item -LiteralPath $outputPath).Length -eq 0) {
        Remove-Item -LiteralPath $outputPath
    }
}

Write-Host "Geschütztes PKCS#12-Paket erstellt: $outputPath"
Write-Host 'Bewahre das neue P12-Passwort getrennt von der Datei im Passwortmanager auf.'
