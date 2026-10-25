# Brings the BAUST Campus stack back up after the PC boots / the user signs in.
# Registered as the "BAUST Campus Autostart" scheduled task (at logon).
# Containers already use `restart: unless-stopped`; this just makes sure the
# Docker engine is running and the stack exists, without rebuilding images.

$ErrorActionPreference = 'Continue'
$project = Split-Path -Parent $PSScriptRoot
$log = Join-Path $env:LOCALAPPDATA 'baust-campus-autostart.log'
function Log($msg) { "$(Get-Date -Format s)  $msg" | Out-File -Append -Encoding utf8 $log }

Log 'autostart triggered'

if (-not (Get-Process 'Docker Desktop' -ErrorAction SilentlyContinue)) {
    Log 'starting Docker Desktop'
    Start-Process "$env:LOCALAPPDATA\Programs\DockerDesktop\Docker Desktop.exe"
}

$deadline = (Get-Date).AddMinutes(10)
do {
    docker info *> $null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 10
} while ((Get-Date) -lt $deadline)

if ($LASTEXITCODE -ne 0) { Log 'docker engine did not come up within 10 minutes'; exit 1 }

Set-Location $project
docker compose up -d 2>&1 | ForEach-Object { "$_" } | Out-File -Append -Encoding utf8 $log
Log "compose up exit code $LASTEXITCODE"
