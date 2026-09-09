[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath

function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & git -C $projectRoot @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Git-Befehl fehlgeschlagen: git $($Arguments -join ' ')"
    }
}

$worktreeStatus = (& git -C $projectRoot status --porcelain)
if ($LASTEXITCODE -ne 0) { throw 'Der Git-Status konnte nicht gelesen werden.' }
if ($worktreeStatus) {
    throw "Der Arbeitsstand enthält uncommittete Änderungen. Bitte zuerst committen oder sichern; es wurde nichts verändert."
}

Invoke-Git fetch origin
Invoke-Git show-ref --verify --quiet refs/heads/dev
Invoke-Git show-ref --verify --quiet refs/heads/main

try {
    Invoke-Git switch main
    Invoke-Git pull --ff-only origin main
    Invoke-Git merge --no-ff dev -m 'Merge branch dev into main'
    Invoke-Git push origin main
    Invoke-Git switch dev
    Write-Host 'Erfolgreich: dev wurde nach main gemergt, main gepusht und wieder zu dev gewechselt.'
}
catch {
    Write-Warning 'Der Vorgang wurde angehalten. Bei einem Merge-Konflikt bitte diesen auf main auflösen, committen und danach main pushen. Ein automatischer Rückwechsel nach dev erfolgt absichtlich nicht.'
    throw
}
