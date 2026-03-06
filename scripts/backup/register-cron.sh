#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
RUN_TIME="${2:-01:00}"
KEEP_DAYS="${KEEP_DAYS:-30}"

IFS=':' read -r HOUR MINUTE <<< "$RUN_TIME"
if [[ -z "${HOUR:-}" || -z "${MINUTE:-}" ]]; then
  echo "Invalid time format. Use HH:MM (example: 01:00)." >&2
  exit 1
fi

SCRIPT_PATH="$PROJECT_ROOT/scripts/backup/backup-nightly.sh"
if [[ ! -f "$SCRIPT_PATH" ]]; then
  echo "Backup script not found: $SCRIPT_PATH" >&2
  exit 1
fi

chmod +x "$SCRIPT_PATH" "$PROJECT_ROOT/scripts/backup/restore-latest.sh" "$PROJECT_ROOT/scripts/backup/register-cron.sh"

CRON_LINE="$MINUTE $HOUR * * * BACKUP_ROOT=\"$PROJECT_ROOT/backups\" KEEP_DAYS=\"$KEEP_DAYS\" \"$SCRIPT_PATH\" \"$PROJECT_ROOT\" >> \"$PROJECT_ROOT/backups/cron.log\" 2>&1"

TMP_CRON="$(mktemp)"
crontab -l 2>/dev/null | grep -v "backup-nightly.sh" > "$TMP_CRON" || true
echo "$CRON_LINE" >> "$TMP_CRON"
crontab "$TMP_CRON"
rm -f "$TMP_CRON"

echo "Cron backup registered successfully."
echo "Schedule: daily at $RUN_TIME"
echo "Command: $CRON_LINE"
