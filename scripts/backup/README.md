# Backup and Restore (Windows + Linux)

## Files
- Windows:
  - `backup-nightly.ps1`: creates DB dump + uploads zip under `backups/<timestamp>/`
  - `restore-latest.ps1`: restores DB from a backup folder (and optionally uploads)
  - `register-task.ps1`: registers nightly backup as a Windows Scheduled Task
- Linux:
  - `backup-nightly.sh`: creates DB dump + uploads tar.gz under `backups/<timestamp>/`
  - `restore-latest.sh`: restores DB from latest backup (and optionally uploads)
  - `register-cron.sh`: registers daily backup in crontab

## Windows (WAMP/XAMPP)

### 1) Run backup now (manual test)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\backup-nightly.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -KeepDays 30
```

### 2) Register nightly automation
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\register-task.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -TaskName "SanEnriqueScholarshipNightlyBackup" -StartTime "01:00" -KeepDays 30
```

### 3) Run restore drill (DB only)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\restore-latest.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship"
```

### 4) Run restore drill (DB + uploads)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\restore-latest.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -RestoreUploads
```

## Linux (VPS/LAMP)

### 1) Make scripts executable
```bash
chmod +x scripts/backup/*.sh
```

### 2) Run backup now
```bash
BACKUP_ROOT="$PWD/backups" KEEP_DAYS=30 ./scripts/backup/backup-nightly.sh "$PWD"
```

### 3) Register daily cron backup at 01:00
```bash
./scripts/backup/register-cron.sh "$PWD" "01:00"
```

### 4) Run restore drill (DB only)
```bash
./scripts/backup/restore-latest.sh "$PWD"
```

### 5) Run restore drill (DB + uploads)
```bash
RESTORE_UPLOADS=1 ./scripts/backup/restore-latest.sh "$PWD" --restore-uploads
```

## Notes
- Scripts read DB credentials from `.env`.
- Windows tools (`mysqldump` and `mysql`) are auto-detected from PATH or `C:\wamp64\bin\mysql\...\bin`.
- Upload restore is non-destructive: current `uploads` is renamed to `uploads_before_restore_<timestamp>`.
