#!/bin/sh
set -e

LOG_DIR=/var/www/html/logs
BACKUP_DIR=/var/www/html/backup
mkdir -p /var/log/nginx /var/log/php "$LOG_DIR" "$BACKUP_DIR"

# Run initial sync once on first startup using environment parameters.
if [ ! -f /var/www/html/.initial_sync_done ]; then
  if [ -f /var/www/html/.env ]; then
    . /var/www/html/.env
  fi
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running initial sync..." >> /var/www/html/logs/sync.log 2>&1
  cd /var/www/html && /usr/local/bin/sync.sh
  touch /var/www/html/.initial_sync_done
fi

if [ -n "$SYNC_CRON" ]; then
  cat > /etc/crontabs/root <<EOF
$SYNC_CRON cd /var/www/html && . /var/www/html/.env && /usr/local/bin/sync.sh >> /var/www/html/logs/cron.log 2>&1
EOF
  crond -l 8
fi

# Use the default php-fpm socket configuration in the PHP image
php-fpm --nodaemonize 2>>/var/log/php/php-fpm.log &

nginx -g 'daemon off;'
