param(
    [string]$ProjectRoot = "",
    [string]$TaskName = "SanEnriqueScholarshipNightlyBackup",
    [string]$StartTime = "01:00",
    [int]$KeepDays = 30
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($ProjectRoot -eq "") {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
}

$backupScript = Join-Path $PSScriptRoot "backup-nightly.ps1"
if (-not (Test-Path -LiteralPath $backupScript)) {
    throw "Backup script not found: $backupScript"
}

$timeValid = [DateTime]::TryParseExact($StartTime, "HH:mm", $null, [System.Globalization.DateTimeStyles]::None, [ref]([datetime]::MinValue))
if (-not $timeValid) {
    throw "Invalid StartTime format. Use HH:mm (example: 01:00)."
}

$taskCommand = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "' +
    $backupScript + '" -ProjectRoot "' + $ProjectRoot + '" -KeepDays ' + $KeepDays

Write-Host "Registering scheduled task:"
Write-Host "  Name: $TaskName"
Write-Host "  Time: $StartTime daily"
Write-Host "  Command: $taskCommand"

$args = @(
    "/Create",
    "/SC", "DAILY",
    "/TN", $TaskName,
    "/TR", $taskCommand,
    "/ST", $StartTime,
    "/RU", "SYSTEM",
    "/F"
)

& schtasks.exe @args | Out-Host
if ($LASTEXITCODE -ne 0) {
    throw "Failed to register scheduled task."
}

Write-Host "Task registered successfully."
Write-Host "You can verify with: schtasks /Query /TN `"$TaskName`" /V /FO LIST"
