[CmdletBinding()]
param(
    [string]$CommitMessage = 'chore: Entwicklungsstand aktualisieren'
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

Invoke-Git fetch origin
Invoke-Git show-ref --verify --quiet refs/heads/dev
Invoke-Git show-ref --verify --quiet refs/heads/main

$currentBranch = (& git -C $projectRoot branch --show-current).Trim()
if ($LASTEXITCODE -ne 0) { throw 'Der aktuelle Git-Branch konnte nicht gelesen werden.' }
if ($currentBranch -ne 'dev') {
    throw "Das Skript muss auf dem Branch 'dev' gestartet werden. Aktuell: '$currentBranch'."
}

$behindDev = [int](& git -C $projectRoot rev-list --count dev..origin/dev)
if ($LASTEXITCODE -ne 0) { throw 'Der Stand von dev konnte nicht mit origin/dev verglichen werden.' }
if ($behindDev -gt 0) {
    throw "Der lokale dev-Branch ist $behindDev Commit(s) hinter origin/dev. Bitte zuerst 'git pull --ff-only origin dev' ausführen; es wurde nichts verändert."
}

try {
    Invoke-Git add .
    & git -C $projectRoot diff --cached --quiet
    $hasStagedChanges = $LASTEXITCODE -eq 1
    if (!$hasStagedChanges -and $LASTEXITCODE -ne 0) {
        throw 'Der gestagte Git-Diff konnte nicht geprüft werden.'
    }
    if ($hasStagedChanges) {
        Invoke-Git commit -m $CommitMessage
    }

    Invoke-Git push origin dev
    Invoke-Git switch main
    Invoke-Git pull --ff-only origin main
    Invoke-Git merge --no-ff dev -m 'Merge branch dev into main'
    Invoke-Git push origin main
    Invoke-Git switch dev
    if ($hasStagedChanges) {
        Write-Host 'Erfolgreich: Änderungen wurden nach dev gepusht, dev nach main gemergt, main gepusht und wieder zu dev gewechselt.'
    } else {
        Write-Host 'Erfolgreich: dev war bereits aktuell, wurde nach main gemergt, main gepusht und wieder zu dev gewechselt.'
    }
}
catch {
    Write-Warning 'Der Vorgang wurde angehalten. Bei einem Merge-Konflikt bitte diesen auf main auflösen, committen und danach main pushen. Ein automatischer Rückwechsel nach dev erfolgt absichtlich nicht.'
    throw
}
