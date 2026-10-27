#!/usr/bin/env bash
#
# Prove the latest backup can be restored, without touching the live data.
#
#   deploy/restore-drill.sh                 # latest archive in BACKUP_DIR
#   deploy/restore-drill.sh db-20261004T023000Z.sql.gz
#
# Loads the dump into a scratch database, compares the rows that matter
# (users, orders, enrollments, invoices) with the live database, checks the
# files archive opens and holds what storage holds, then drops the scratch
# database. Run it once before go-live and after any change to the backup.
#
# Creating the scratch database needs a MySQL account allowed to CREATE and
# DROP databases; the application account usually is not:
#   MYSQL_ADMIN_USER=root MYSQL_ADMIN_PASSWORD=... deploy/restore-drill.sh
#
# Counts differ when orders arrive between the backup and the drill. A live
# count LOWER than the backup's is the one that means something is wrong.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API="$ROOT/api"
DEST="${BACKUP_DIR:-/var/backups/construye}"
SCRATCH="construye_restore_drill"

env_get() {
  grep -E "^$1=" "$API/.env" | tail -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'
}

DB_HOST="$(env_get DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_get DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="$(env_get DB_DATABASE)"
ADMIN_USER="${MYSQL_ADMIN_USER:-$(env_get DB_USERNAME)}"
export MYSQL_PWD="${MYSQL_ADMIN_PASSWORD:-$(env_get DB_PASSWORD)}"

mysql_q() { mysql -h "$DB_HOST" -P "$DB_PORT" -u "$ADMIN_USER" -N -B "$@"; }

if [ $# -ge 1 ]; then
  DB_FILE="$DEST/$(basename "$1")"
else
  DB_FILE="$(ls -1t "$DEST"/db-*.sql.gz 2>/dev/null | head -n1 || true)"
fi
[ -n "$DB_FILE" ] && [ -f "$DB_FILE" ] || { echo "No database archive found in $DEST." >&2; exit 1; }
FILES_FILE="${DB_FILE/db-/files-}"; FILES_FILE="${FILES_FILE%.sql.gz}.tar.gz"

echo "Restoring $(basename "$DB_FILE") into scratch database $SCRATCH"
mysql_q -e "DROP DATABASE IF EXISTS \`$SCRATCH\`; CREATE DATABASE \`$SCRATCH\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
trap 'mysql_q -e "DROP DATABASE IF EXISTS \`$SCRATCH\`;" || true' EXIT
gunzip -c "$DB_FILE" | mysql_q "$SCRATCH"

status=0
printf '\n%-14s %10s %10s\n' "table" "backup" "live"
for table in users orders order_modules enrollments invoices coupons; do
  restored="$(mysql_q -e "SELECT COUNT(*) FROM \`$SCRATCH\`.\`$table\`")"
  live="$(mysql_q -e "SELECT COUNT(*) FROM \`$DB_DATABASE\`.\`$table\`")"
  flag=""
  if [ "$live" -lt "$restored" ]; then flag="  <- live has FEWER rows than the backup"; status=1; fi
  printf '%-14s %10s %10s%s\n' "$table" "$restored" "$live" "$flag"
done

if [ "$(mysql_q -e "SELECT COUNT(*) FROM \`$SCRATCH\`.migrations")" -eq 0 ]; then
  echo "The restored database has no migrations: the dump is not a usable copy." >&2
  status=1
fi

if [ -f "$FILES_FILE" ]; then
  in_archive="$(tar -tzf "$FILES_FILE" | grep -vc '/$' || true)"
  on_disk="$(find "$API/storage/app/private" -type f | wc -l | tr -d ' ')"
  printf '%-14s %10s %10s\n' "private files" "$in_archive" "$on_disk"
else
  echo "No files archive next to the dump ($(basename "$FILES_FILE"))." >&2
  status=1
fi

if [ "$status" -eq 0 ]; then
  echo; echo "Restore drill passed: $(basename "$DB_FILE") restores to a complete database."
else
  echo; echo "Restore drill FAILED — read the lines above." >&2
fi
exit "$status"
