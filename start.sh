#!/bin/sh
set -e

LOG_DIR=/var/www/html/logs
BACKUP_DIR=/var/www/html/backup
mkdir -p /var/log/nginx /var/log/php "$LOG_DIR" "$BACKUP_DIR"

if [ -n "$SYNC_CRON" ]; then
  cat > /etc/crontabs/root <<EOF
$SYNC_CRON cd /var/www/html && . /var/www/html/.env && /usr/local/bin/sync.sh >> /var/www/html/logs/cron.log 2>&1
EOF
  crond -l 8
fi

# Use the default php-fpm socket configuration in the PHP image
php-fpm --nodaemonize 2>>/var/log/php/php-fpm.log &

nginx -g 'daemon off;'
