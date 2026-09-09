[CmdletBinding()]
param(
    [string]$HostName = '185.164.4.248',
    [string]$UserName = 'aneger',
    [string]$RemoteRoot = '/var/www/html/aesculapp',
    [string]$KeyPath = (Join-Path $env:USERPROFILE '.ssh\aesculapp_demo_deploy')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $KeyPath)) {
    throw "SSH-Schlüssel nicht gefunden: $KeyPath"
}

$serverDirectory = "$RemoteRoot/server"
$remoteCommand = "cd '$serverDirectory' && APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction && APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:status --no-interaction"

Write-Host "Aktualisiere die Datenbank auf $HostName …"
& ssh -o IdentitiesOnly=yes -i $KeyPath "$UserName@$HostName" $remoteCommand

if ($LASTEXITCODE -ne 0) {
    throw "Die Remote-Migration ist fehlgeschlagen (SSH Exit-Code $LASTEXITCODE)."
}

Write-Host 'Remote-Datenbank erfolgreich geprüft und migriert.'
