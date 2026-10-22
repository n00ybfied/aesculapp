param(
    [string]$LaragonRoot = ''
)

$ErrorActionPreference = 'Stop'

$repositoryRoot = $PSScriptRoot
$signingDirectory = Join-Path $repositoryRoot '.runtime\ios-signing'
$privateKeyPath = Join-Path $signingDirectory 'testflight-distribution.key.pem'
$requestPath = Join-Path $signingDirectory 'testflight-distribution.certSigningRequest'

if (Test-Path -LiteralPath $privateKeyPath -PathType Leaf -ErrorAction SilentlyContinue) {
    throw "Ein privater Schlüssel existiert bereits: $privateKeyPath. Er wird nicht überschrieben."
}
if (Test-Path -LiteralPath $requestPath -PathType Leaf -ErrorAction SilentlyContinue) {
    throw "Eine Zertifikatsanfrage existiert bereits: $requestPath. Sie wird nicht überschrieben."
}

if ($LaragonRoot -eq '') {
    $LaragonRoot = if ($env:LARAGON_ROOT) {
        $env:LARAGON_ROOT
    } else {
        Join-Path (Split-Path -Path $repositoryRoot -Qualifier) 'laragon'
    }
}

$opensslPath = Join-Path $LaragonRoot 'bin\git\usr\bin\openssl.exe'
if (-not (Test-Path -LiteralPath $opensslPath -PathType Leaf)) {
    throw "OpenSSL wurde nicht gefunden: $opensslPath. Bei Bedarf -LaragonRoot angeben."
}

$appleEmail = (Read-Host 'E-Mail-Adresse deines Apple-Developer-Kontos für die Zertifikatsanfrage').Trim()
if ($appleEmail -notmatch '^[^@\s/]+@[^@\s/]+\.[^@\s/]+$') {
    throw 'Bitte eine gültige E-Mail-Adresse ohne Leerzeichen oder Schrägstrich eingeben.'
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

$securePassphrase = Read-Host 'Passwort für den privaten Schlüssel (Eingabe bleibt unsichtbar)' -AsSecureString
if ($securePassphrase.Length -lt 12) {
    throw 'Das Passwort muss mindestens 12 Zeichen lang sein. Es wurde noch kein Schlüssel erzeugt.'
}
$secureConfirmation = Read-Host 'Passwort wiederholen' -AsSecureString
$passphraseText = ConvertFrom-SecureStringForOpenSsl $securePassphrase
$confirmationText = ConvertFrom-SecureStringForOpenSsl $secureConfirmation
if ($passphraseText -cne $confirmationText) {
    $passphraseText = $null
    $confirmationText = $null
    throw 'Die Passwörter stimmen nicht überein. Es wurde noch kein Schlüssel erzeugt.'
}
$confirmationText = $null
$securePassphrase.Dispose()
$secureConfirmation.Dispose()

New-Item -ItemType Directory -Path $signingDirectory -Force | Out-Null
$env:AESCULAPP_IOS_KEY_PASSPHRASE = $passphraseText
$passphraseText = $null

try {
    & $opensslPath genpkey -algorithm RSA -aes-256-cbc -pkeyopt rsa_keygen_bits:2048 -pass env:AESCULAPP_IOS_KEY_PASSPHRASE -out $privateKeyPath
    if ($LASTEXITCODE -ne 0) {
        throw 'Der private Schlüssel konnte nicht erstellt werden.'
    }

    & $opensslPath req -new -sha256 -key $privateKeyPath -passin env:AESCULAPP_IOS_KEY_PASSPHRASE -out $requestPath -subj "/emailAddress=$appleEmail/CN=AesculApp TestFlight Distribution"
    if ($LASTEXITCODE -ne 0) {
        throw 'Die Zertifikatsanfrage konnte nicht erstellt werden. Der private Schlüssel bleibt erhalten.'
    }
} finally {
    Remove-Item Env:\AESCULAPP_IOS_KEY_PASSPHRASE -ErrorAction SilentlyContinue
    foreach ($generatedPath in @($privateKeyPath, $requestPath)) {
        if ((Test-Path -LiteralPath $generatedPath -PathType Leaf) -and (Get-Item -LiteralPath $generatedPath).Length -eq 0) {
            Remove-Item -LiteralPath $generatedPath
        }
    }
}

& $opensslPath req -in $requestPath -noout -verify
if ($LASTEXITCODE -ne 0) {
    throw 'Die Zertifikatsanfrage konnte nicht verifiziert werden.'
}
$requestDetails = & $opensslPath req -in $requestPath -noout -text
if ($LASTEXITCODE -ne 0 -or ($requestDetails -join "`n") -notmatch 'Public-Key:\s*\(2048 bit\)') {
    throw 'Die Zertifikatsanfrage hat nicht die von Apple erwartete RSA-Schlüssellänge von 2048 Bit.'
}

Write-Host "Zertifikatsanfrage: $requestPath"
Write-Host "Privater Schlüssel (niemals hochladen): $privateKeyPath"
