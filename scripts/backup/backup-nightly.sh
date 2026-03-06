#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
BACKUP_ROOT="${BACKUP_ROOT:-$PROJECT_ROOT/backups}"
KEEP_DAYS="${KEEP_DAYS:-30}"

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

DB_HOST="$(read_env_value "DB_HOST" "127.0.0.1")"
DB_PORT="$(read_env_value "DB_PORT" "3306")"
DB_NAME="$(read_env_value "DB_NAME" "lgu_scholarship")"
DB_USER="$(read_env_value "DB_USER" "root")"
DB_PASS="$(read_env_value "DB_PASS" "")"

timestamp="$(date +%Y%m%d_%H%M%S)"
backup_dir="$BACKUP_ROOT/$timestamp"
db_file="$backup_dir/database.sql"
uploads_file="$backup_dir/uploads.tar.gz"
manifest_file="$backup_dir/manifest.txt"
log_file="$backup_dir/backup.log"

mkdir -p "$backup_dir"

{
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup started."
  if [[ -n "$DB_PASS" ]]; then
    MYSQL_PWD="$DB_PASS" mysqldump \
      --host="$DB_HOST" \
      --port="$DB_PORT" \
      --user="$DB_USER" \
      --single-transaction \
      --routines \
      --triggers \
      --default-character-set=utf8mb4 \
      "$DB_NAME" > "$db_file"
  else
    mysqldump \
      --host="$DB_HOST" \
      --port="$DB_PORT" \
      --user="$DB_USER" \
      --single-transaction \
      --routines \
      --triggers \
      --default-character-set=utf8mb4 \
      "$DB_NAME" > "$db_file"
  fi
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Database dump saved: $db_file"

  tar -czf "$uploads_file" -C "$PROJECT_ROOT" uploads
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Uploads archive saved: $uploads_file"

  db_hash="$(sha256sum "$db_file" | awk '{print $1}')"
  uploads_hash="$(sha256sum "$uploads_file" | awk '{print $1}')"

  cat > "$manifest_file" <<EOF
timestamp=$timestamp
project_root=$PROJECT_ROOT
db_host=$DB_HOST
db_port=$DB_PORT
db_name=$DB_NAME
db_user=$DB_USER
database_file=database.sql
database_sha256=$db_hash
uploads_file=uploads.tar.gz
uploads_sha256=$uploads_hash
EOF
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Manifest saved: $manifest_file"

  if [[ "$KEEP_DAYS" =~ ^[0-9]+$ ]] && [[ "$KEEP_DAYS" -gt 0 ]]; then
    find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Old backups older than $KEEP_DAYS days removed."
  fi

  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup completed successfully."
} | tee -a "$log_file"

