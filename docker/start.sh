#!/bin/sh
set -e

LOG_DIR=/var/www/html/logs
BACKUP_DIR=/var/www/html/backup
mkdir -p /var/log/nginx /var/log/php "$LOG_DIR" "$BACKUP_DIR"

cat > /usr/local/bin/run-sync.sh <<'EOF'
#!/bin/sh

if [ -f /var/www/html/.env ]; then
  while IFS='=' read -r key value; do
    case "$key" in
      ''|\#*) continue ;;
      export*) key=${key#export } ;;
    esac
    export "$key=$value"
  done < /var/www/html/.env
fi

cd /var/www/html
/usr/local/bin/sync.sh
EOF
chmod +x /usr/local/bin/run-sync.sh

# Run initial sync once on first startup using environment parameters.
if [ ! -f /var/www/html/.initial_sync_done ]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running initial sync..." >> /var/www/html/logs/sync.log 2>&1
  /usr/local/bin/run-sync.sh >> /var/www/html/logs/sync.log 2>&1
  touch /var/www/html/.initial_sync_done
fi

if [ -n "$SYNC_CRON" ]; then
  cat > /etc/crontabs/root <<EOF
$SYNC_CRON /usr/local/bin/run-sync.sh >> /var/www/html/logs/cron.log 2>&1
EOF
  crond -l 8
fi

# Use the default php-fpm socket configuration in the PHP image
php-fpm --nodaemonize 2>>/var/log/php/php-fpm.log &

nginx -g 'daemon off;'
