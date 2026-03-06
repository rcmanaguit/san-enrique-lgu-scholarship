param(
    [string]$ProjectRoot = "",
    [string]$BackupRoot = "",
    [string]$BackupStamp = "",
    [switch]$RestoreUploads
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-Info([string]$Message) {
    $ts = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$ts] $Message"
}

function Get-EnvMap([string]$EnvFilePath) {
    $result = @{}
    if (-not (Test-Path -LiteralPath $EnvFilePath)) {
        return $result
    }
    foreach ($line in Get-Content -LiteralPath $EnvFilePath) {
        $trimmed = $line.Trim()
        if ($trimmed -eq "" -or $trimmed.StartsWith("#")) {
            continue
        }
        $parts = $trimmed.Split("=", 2)
        if ($parts.Count -ne 2) {
            continue
        }
        $key = $parts[0].Trim()
        $value = $parts[1].Trim()
        if ($value.StartsWith('"') -and $value.EndsWith('"') -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        if ($value.StartsWith("'") -and $value.EndsWith("'") -and $value.Length -ge 2) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $result[$key] = $value
    }
    return $result
}

function Get-ToolPath([string]$ToolName, [string]$WampRoot) {
    $fromPath = (Get-Command $ToolName -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Source)
    if ($fromPath) {
        return $fromPath
    }

    if (Test-Path -LiteralPath $WampRoot) {
        $candidate = Get-ChildItem -LiteralPath (Join-Path $WampRoot "bin\mysql") -Directory -ErrorAction SilentlyContinue |
            Sort-Object Name -Descending |
            ForEach-Object { Join-Path $_.FullName ("bin\" + $ToolName + ".exe") } |
            Where-Object { Test-Path -LiteralPath $_ } |
            Select-Object -First 1
        if ($candidate) {
            return $candidate
        }
    }

    return ""
}

if ($ProjectRoot -eq "") {
    $ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
}
if ($BackupRoot -eq "") {
    $BackupRoot = Join-Path $ProjectRoot "backups"
}
if (-not (Test-Path -LiteralPath $BackupRoot)) {
    throw "Backup folder not found: $BackupRoot"
}

if ($BackupStamp -eq "") {
    $latest = Get-ChildItem -LiteralPath $BackupRoot -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending |
        Select-Object -First 1
    if (-not $latest) {
        throw "No backup folders found in $BackupRoot"
    }
    $BackupStamp = $latest.Name
}

$targetBackupDir = Join-Path $BackupRoot $BackupStamp
if (-not (Test-Path -LiteralPath $targetBackupDir)) {
    throw "Backup folder not found: $targetBackupDir"
}

$dbFile = Join-Path $targetBackupDir "database.sql"
$uploadsZip = Join-Path $targetBackupDir "uploads.zip"
if (-not (Test-Path -LiteralPath $dbFile)) {
    throw "Database backup file missing: $dbFile"
}
if ($RestoreUploads -and -not (Test-Path -LiteralPath $uploadsZip)) {
    throw "Uploads archive missing: $uploadsZip"
}

$envMap = Get-EnvMap (Join-Path $ProjectRoot ".env")
$dbHost = if ($envMap.ContainsKey("DB_HOST")) { $envMap["DB_HOST"] } else { "127.0.0.1" }
$dbPort = if ($envMap.ContainsKey("DB_PORT")) { $envMap["DB_PORT"] } else { "3306" }
$dbName = if ($envMap.ContainsKey("DB_NAME")) { $envMap["DB_NAME"] } else { "lgu_scholarship" }
$dbUser = if ($envMap.ContainsKey("DB_USER")) { $envMap["DB_USER"] } else { "root" }
$dbPass = if ($envMap.ContainsKey("DB_PASS")) { $envMap["DB_PASS"] } else { "" }

$wampRoot = "C:\wamp64"
$mysqlPath = Get-ToolPath -ToolName "mysql" -WampRoot $wampRoot
if ($mysqlPath -eq "") {
    throw "mysql client not found. Install MySQL CLI or update PATH."
}

Write-Info "Restoring database from: $dbFile"
$mysqlArgs = @(
    "--host=$dbHost",
    "--port=$dbPort",
    "--user=$dbUser",
    "--default-character-set=utf8mb4"
)
if ($dbPass -ne "") {
    $mysqlArgs += "--password=$dbPass"
}
$mysqlArgs += $dbName

$joinedArgs = ($mysqlArgs | ForEach-Object { '"' + ($_ -replace '"', '\"') + '"' }) -join " "
$commandLine = '"' + $mysqlPath + '" ' + $joinedArgs + ' < "' + $dbFile + '"'
cmd.exe /c $commandLine | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Database restore failed."
}
Write-Info "Database restore completed."

if ($RestoreUploads) {
    $uploadsPath = Join-Path $ProjectRoot "uploads"
    if (-not (Test-Path -LiteralPath $uploadsPath)) {
        throw "Uploads folder not found: $uploadsPath"
    }
    $rollbackName = "uploads_before_restore_" + (Get-Date -Format "yyyyMMdd_HHmmss")
    $rollbackPath = Join-Path $ProjectRoot $rollbackName
    Move-Item -LiteralPath $uploadsPath -Destination $rollbackPath
    New-Item -ItemType Directory -Path $uploadsPath | Out-Null

    Write-Info "Restoring uploads from: $uploadsZip"
    Expand-Archive -LiteralPath $uploadsZip -DestinationPath $uploadsPath -Force
    Write-Info "Uploads restore completed. Previous uploads moved to: $rollbackPath"
}

Write-Info "Restore operation completed successfully."
