#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
BACKUP_ROOT="${BACKUP_ROOT:-$PROJECT_ROOT/backups}"
BACKUP_STAMP="${BACKUP_STAMP:-}"
RESTORE_UPLOADS="${RESTORE_UPLOADS:-0}"

if [[ "${2:-}" == "--restore-uploads" ]]; then
  RESTORE_UPLOADS=1
fi

ENV_FILE="$PROJECT_ROOT/.env"

read_env_value() {
  local key="$1"
  local default_value="$2"
  if [[ ! -f "$ENV_FILE" ]]; then
    printf '%s' "$default_value"
    return
  fi
  local value
  value="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2- || true)"
  value="${value%\"}"
  value="${value#\"}"
  value="${value%\'}"
  value="${value#\'}"
  if [[ -z "$value" ]]; then
    printf '%s' "$default_value"
  else
    printf '%s' "$value"
  fi
}

if [[ ! -d "$BACKUP_ROOT" ]]; then
  echo "Backup folder not found: $BACKUP_ROOT" >&2
  exit 1
fi

if [[ -z "$BACKUP_STAMP" ]]; then
  BACKUP_STAMP="$(find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r | head -n 1)"
fi

if [[ -z "$BACKUP_STAMP" ]]; then
  echo "No backup folders found in $BACKUP_ROOT" >&2
  exit 1
fi

backup_dir="$BACKUP_ROOT/$BACKUP_STAMP"
db_file="$backup_dir/database.sql"
uploads_file="$backup_dir/uploads.tar.gz"

if [[ ! -f "$db_file" ]]; then
  echo "Database backup file missing: $db_file" >&2
  exit 1
fi

if [[ "$RESTORE_UPLOADS" == "1" ]] && [[ ! -f "$uploads_file" ]]; then
  echo "Uploads archive missing: $uploads_file" >&2
  exit 1
fi

DB_HOST="$(read_env_value "DB_HOST" "127.0.0.1")"
DB_PORT="$(read_env_value "DB_PORT" "3306")"
DB_NAME="$(read_env_value "DB_NAME" "lgu_scholarship")"
DB_USER="$(read_env_value "DB_USER" "root")"
DB_PASS="$(read_env_value "DB_PASS" "")"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restoring DB from $db_file"
if [[ -n "$DB_PASS" ]]; then
  MYSQL_PWD="$DB_PASS" mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --default-character-set=utf8mb4 \
    "$DB_NAME" < "$db_file"
else
  mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --default-character-set=utf8mb4 \
    "$DB_NAME" < "$db_file"
fi
echo "[$(date '+%Y-%m-%d %H:%M:%S')] DB restore completed."

if [[ "$RESTORE_UPLOADS" == "1" ]]; then
  uploads_dir="$PROJECT_ROOT/uploads"
  if [[ ! -d "$uploads_dir" ]]; then
    echo "Uploads folder not found: $uploads_dir" >&2
    exit 1
  fi
  rollback_dir="$PROJECT_ROOT/uploads_before_restore_$(date +%Y%m%d_%H%M%S)"
  mv "$uploads_dir" "$rollback_dir"
  mkdir -p "$uploads_dir"
  tar -xzf "$uploads_file" -C "$PROJECT_ROOT"
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Upload restore completed. Previous uploads moved to $rollback_dir"
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Restore operation completed successfully."

