[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSCommandPath
$runtimeDirectory = Join-Path $projectRoot '.runtime'
$pidFile = Join-Path $runtimeDirectory 'dev-processes.json'

function Stop-ProcessTree {
    param([int]$ProcessId)
    $children = Get-CimInstance Win32_Process -Filter "ParentProcessId = $ProcessId" -ErrorAction SilentlyContinue
    foreach ($child in $children) { Stop-ProcessTree -ProcessId $child.ProcessId }
    Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
}

function Stop-PreviousDevelopmentServers {
    if (Test-Path $pidFile) {
        $previous = Get-Content -Raw $pidFile | ConvertFrom-Json
        foreach ($processId in $previous.processIds) { Stop-ProcessTree -ProcessId $processId }
        Remove-Item -LiteralPath $pidFile -Force
    }

    # Only stop manually started processes when their command line identifies one
    # of this project's exact development server commands and ports.
    $patterns = @(
        'ng serve.*--port 4200',
        'ng serve.*--port 4201',
        'php.*-S localhost:6080.*public/index.php'
    )
    Get-CimInstance Win32_Process | Where-Object {
        $_.CommandLine -and ($patterns | Where-Object { $_.CommandLine -match $_ })
    } | ForEach-Object { Stop-ProcessTree -ProcessId $_.ProcessId }

    # `ng serve` without an explicit --port uses 4200. Detect that concrete
    # listener as well, but only when it is clearly an Angular CLI process.
    Get-NetTCPConnection -LocalPort 4200 -State Listen -ErrorAction SilentlyContinue | ForEach-Object {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($_.OwningProcess)"
        if ($process.CommandLine -match '(ng serve|@angular[\\/]cli|[\\/]ng\.js)') {
            Stop-ProcessTree -ProcessId $process.ProcessId
        }
    }
}

function Start-DevelopmentServer {
    param([string]$Name, [string]$Command)
    $process = Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $Command -WorkingDirectory $projectRoot -PassThru -WindowStyle Hidden
    Write-Host "Started $Name (PID $($process.Id))."
    return $process.Id
}

New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $projectRoot 'server\var\log') -Force | Out-Null
Stop-PreviousDevelopmentServers

$clientId = Start-DevelopmentServer 'customer app' 'cd /d client && npm.cmd start -- --port 4200'
$adminId = Start-DevelopmentServer 'admin portal' 'cd /d admin && npm.cmd start -- --port 4201'
$serverId = Start-DevelopmentServer 'Symfony API' 'cd /d server && php -S localhost:6080 -t public public/index.php >> var\log\php-server.log 2>&1'

@{ processIds = @($clientId, $adminId, $serverId) } | ConvertTo-Json | Set-Content -LiteralPath $pidFile -Encoding utf8
Write-Host ''
Write-Host 'Customer app: http://localhost:4200'
Write-Host 'Admin portal: http://localhost:4201'
Write-Host 'API:          http://localhost:6080/api/health'
Write-Host 'PHP server log: server\var\log\php-server.log'
Write-Host 'Symfony log:    server\var\log\dev.log'
Write-Host 'QR audit log:   server\var\log\qr.log'
