[CmdletBinding()]
param(
    [string]$MergeMessage = 'chore: Stabilen Stand fuer Apotheken-Test uebernehmen'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath

function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & git -C $projectRoot @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Git-Befehl fehlgeschlagen: git $($Arguments -join ' ')"
    }
}

$startingBranch = (& git -C $projectRoot branch --show-current).Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($startingBranch)) {
    throw 'Der aktuelle Git-Branch konnte nicht gelesen werden.'
}

$changes = & git -C $projectRoot status --porcelain
if ($LASTEXITCODE -ne 0) { throw 'Der Arbeitsbaum konnte nicht geprueft werden.' }
if ($changes) {
    throw 'Der Arbeitsbaum ist nicht sauber. Bitte Aenderungen zuerst committen oder sichern.'
}

Invoke-Git fetch origin
Invoke-Git show-ref --verify --quiet refs/heads/main
Invoke-Git show-ref --verify --quiet refs/heads/apotheke-test

try {
    Invoke-Git switch main
    Invoke-Git pull --ff-only origin main
    Invoke-Git switch apotheke-test
    Invoke-Git pull --ff-only origin apotheke-test
    Invoke-Git merge --no-ff main -m $MergeMessage
    Invoke-Git push origin apotheke-test
    Invoke-Git switch $startingBranch
    Write-Host 'Erfolgreich: main wurde nach apotheke-test uebernommen und der Branch gepusht. Der Apotheken-Deploy startet nur bei aktivierter GitHub-Umgebung.'
}
catch {
    Write-Warning 'Vorgang angehalten. Bitte Branch und Merge-Status pruefen. Kein Force-Push und kein automatischer Merge-Abbruch.'
    throw
}
