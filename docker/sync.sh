#!/bin/sh
set -e

SYNC_COUNTRIES=${SYNC_COUNTRIES:-CN,US}
SYNC_TARGET_DIR=${SYNC_TARGET_DIR:-/var/www/html/playlists}
SYNC_BACKUP_DIR=${SYNC_BACKUP_DIR:-/var/www/html/backup}
SYNC_NO_BACKUP=${SYNC_NO_BACKUP:-false}
SYNC_SHOW_BROKEN=${SYNC_SHOW_BROKEN:-false}
SYNC_PAGE_SIZE=${SYNC_PAGE_SIZE:-500}
SYNC_TIMEOUT=${SYNC_TIMEOUT:-120}
SYNC_PROXY=${SYNC_PROXY:-false}

if [ -z "$SYNC_COUNTRIES" ]; then
  echo "SYNC_COUNTRIES is required"
  exit 1
fi

mkdir -p "$SYNC_TARGET_DIR" "$SYNC_BACKUP_DIR" /var/www/html/logs
LOG_FILE=/var/www/html/logs/sync.log

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting sync: $SYNC_COUNTRIES" >> "$LOG_FILE"

echo "Running syncInternetRatio.py with target=$SYNC_TARGET_DIR backup=$SYNC_BACKUP_DIR" >> "$LOG_FILE"
set -- "$SYNC_COUNTRIES" "--target-dir" "$SYNC_TARGET_DIR" "--backup-dir" "$SYNC_BACKUP_DIR"
[ "$SYNC_NO_BACKUP" = "true" ] && set -- "$@" --no-backup
[ "$SYNC_SHOW_BROKEN" = "true" ] && set -- "$@" --show-broken
[ -n "$SYNC_PAGE_SIZE" ] && set -- "$@" --page-size "$SYNC_PAGE_SIZE"
[ -n "$SYNC_TIMEOUT" ] && set -- "$@" --timeout "$SYNC_TIMEOUT"
[ "$SYNC_PROXY" = "true" ] && set -- "$@" --proxy

python3 /var/www/html/scripts/syncInternetRatio.py "$@" >> "$LOG_FILE" 2>&1
STATUS=$?

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sync finished with status $STATUS" >> "$LOG_FILE"

exit $STATUS
