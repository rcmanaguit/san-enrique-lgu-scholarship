param(
    [string]$ProjectRoot = "",
    [string]$BackupRoot = "",
    [int]$KeepDays = 30
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

$envMap = Get-EnvMap (Join-Path $ProjectRoot ".env")
$dbHost = if ($envMap.ContainsKey("DB_HOST")) { $envMap["DB_HOST"] } else { "127.0.0.1" }
$dbPort = if ($envMap.ContainsKey("DB_PORT")) { $envMap["DB_PORT"] } else { "3306" }
$dbName = if ($envMap.ContainsKey("DB_NAME")) { $envMap["DB_NAME"] } else { "lgu_scholarship" }
$dbUser = if ($envMap.ContainsKey("DB_USER")) { $envMap["DB_USER"] } else { "root" }
$dbPass = if ($envMap.ContainsKey("DB_PASS")) { $envMap["DB_PASS"] } else { "" }

$wampRoot = "C:\wamp64"
$mysqldumpPath = Get-ToolPath -ToolName "mysqldump" -WampRoot $wampRoot

if ($mysqldumpPath -eq "") {
    throw "mysqldump not found. Install MySQL CLI or update PATH."
}

if (-not (Test-Path -LiteralPath $BackupRoot)) {
    New-Item -ItemType Directory -Path $BackupRoot | Out-Null
}

$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupDir = Join-Path $BackupRoot $stamp
New-Item -ItemType Directory -Path $backupDir | Out-Null

$dbDumpFile = Join-Path $backupDir "database.sql"
$uploadsZip = Join-Path $backupDir "uploads.zip"
$manifestFile = Join-Path $backupDir "manifest.txt"
$logFile = Join-Path $backupDir "backup.log"

Write-Info "Backup started." | Tee-Object -LiteralPath $logFile -Append
Write-Info "Using mysqldump: $mysqldumpPath" | Tee-Object -LiteralPath $logFile -Append

$dumpArgs = @(
    "--host=$dbHost",
    "--port=$dbPort",
    "--user=$dbUser",
    "--single-transaction",
    "--routines",
    "--triggers",
    "--default-character-set=utf8mb4"
)

if ($dbPass -ne "") {
    $dumpArgs += "--password=$dbPass"
}
$dumpArgs += $dbName

$dumpOutput = & $mysqldumpPath @dumpArgs 2>&1
if ($LASTEXITCODE -ne 0) {
    $dumpOutput | Out-File -LiteralPath $logFile -Append -Encoding UTF8
    throw "Database dump failed. See $logFile"
}
$dumpOutput | Out-File -LiteralPath $dbDumpFile -Encoding UTF8
Write-Info "Database dump saved: $dbDumpFile" | Tee-Object -LiteralPath $logFile -Append

$uploadsPath = Join-Path $ProjectRoot "uploads"
if (-not (Test-Path -LiteralPath $uploadsPath)) {
    throw "Uploads folder not found: $uploadsPath"
}

Compress-Archive -Path (Join-Path $uploadsPath "*") -DestinationPath $uploadsZip -CompressionLevel Optimal -Force
Write-Info "Uploads archive saved: $uploadsZip" | Tee-Object -LiteralPath $logFile -Append

$dbHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $dbDumpFile).Hash
$uploadsHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $uploadsZip).Hash

$manifest = @(
    "timestamp=$stamp",
    "project_root=$ProjectRoot",
    "db_host=$dbHost",
    "db_port=$dbPort",
    "db_name=$dbName",
    "db_user=$dbUser",
    "database_file=database.sql",
    "database_sha256=$dbHash",
    "uploads_file=uploads.zip",
    "uploads_sha256=$uploadsHash"
)
$manifest | Out-File -LiteralPath $manifestFile -Encoding UTF8
Write-Info "Manifest saved: $manifestFile" | Tee-Object -LiteralPath $logFile -Append

if ($KeepDays -gt 0) {
    $cutoff = (Get-Date).AddDays(-1 * $KeepDays)
    Get-ChildItem -LiteralPath $BackupRoot -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -lt $cutoff } |
        ForEach-Object {
            Write-Info "Removing old backup: $($_.FullName)" | Tee-Object -LiteralPath $logFile -Append
            Remove-Item -LiteralPath $_.FullName -Recurse -Force
        }
}

Write-Info "Backup completed successfully." | Tee-Object -LiteralPath $logFile -Append
