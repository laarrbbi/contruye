#!/usr/bin/env bash
#
# Nightly backup: the database and the private files (CCP receipts, lesson
# attachments). Orders and invoices are records the business is legally
# required to keep; a receipt is the only proof a CCP transfer happened.
#
#   deploy/backup.sh
#
# cron (as the deploy user):
#   30 2 * * * /var/www/construye/deploy/backup.sh >> /var/log/construye-backup.log 2>&1
#
# Environment overrides:
#   BACKUP_DIR=/var/backups/construye     where archives are written
#   KEEP_DAYS=30                     local retention
#   BACKUP_RSYNC_TARGET=user@host:/backups/construye
#       copy every archive off this machine. A backup on the server it backs
#       up does not survive the failure it is for: set this before go-live.
#
# Restoring is rehearsed by deploy/restore-drill.sh. An untested backup is not
# a backup.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API="$ROOT/api"
DEST="${BACKUP_DIR:-/var/backups/construye}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

env_get() {
  grep -E "^$1=" "$API/.env" | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'
}

DB_HOST="$(env_get DB_HOST)"
DB_PORT="$(env_get DB_PORT)"
DB_DATABASE="$(env_get DB_DATABASE)"
DB_USERNAME="$(env_get DB_USERNAME)"
# Via the environment, never the command line, where `ps` would show it.
export MYSQL_PWD="$(env_get DB_PASSWORD)"

mkdir -p "$DEST"
umask 077   # archives hold personal data and payment proofs

DB_FILE="$DEST/db-$STAMP.sql.gz"
FILES_FILE="$DEST/files-$STAMP.tar.gz"

# --single-transaction: a consistent snapshot without locking InnoDB tables,
# so the site keeps taking orders while this runs.
mysqldump --single-transaction --routines --triggers --no-tablespaces \
  -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE" \
  | gzip -9 > "$DB_FILE.part"
mv "$DB_FILE.part" "$DB_FILE"

tar -czf "$FILES_FILE.part" -C "$API/storage/app" private
mv "$FILES_FILE.part" "$FILES_FILE"

# A dump that stops early still gzips cleanly; the last line proves it finished.
if ! gunzip -c "$DB_FILE" | tail -n1 | grep -q 'Dump completed'; then
  echo "$(date -u +%FT%TZ) ERROR $DB_FILE is incomplete." >&2
  exit 1
fi

find "$DEST" -maxdepth 1 -type f \( -name 'db-*.sql.gz' -o -name 'files-*.tar.gz' \) -mtime "+$KEEP_DAYS" -delete

if [ -n "${BACKUP_RSYNC_TARGET:-}" ]; then
  rsync -a "$DEST/" "$BACKUP_RSYNC_TARGET/"
fi

echo "$(date -u +%FT%TZ) OK $(du -h "$DB_FILE" | cut -f1) database, $(du -h "$FILES_FILE" | cut -f1) files${BACKUP_RSYNC_TARGET:+, copied off-site}"
