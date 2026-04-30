#!/bin/sh
set -e

LOG_DIR=/var/www/html/logs
BACKUP_DIR=/var/www/html/backup
mkdir -p "$LOG_DIR" "$BACKUP_DIR"

load_env() {
  if [ -f /var/www/html/.env ]; then
    while IFS='=' read -r key value; do
      case "$key" in
        ''|\#*) continue ;;
        export*) key=${key#export } ;;
      esac
      export "$key=$value"
    done < /var/www/html/.env
  fi
}

load_env

HTTP_PORT=${HTTP_PORT:-18882}
HTTPS_PORT=${HTTPS_PORT:-18883}
SSL_CERT_PATH=${SSL_CERT_PATH:-/etc/nginx/ssl/server.crt}
SSL_KEY_PATH=${SSL_KEY_PATH:-/etc/nginx/ssl/server.key}
SSL_PROTOCOLS=${SSL_PROTOCOLS:-TLSv1.2 TLSv1.3}
SSL_CIPHERS=${SSL_CIPHERS:-HIGH:!aNULL:!MD5}
SSL_SESSION_CACHE=${SSL_SESSION_CACHE:-shared:SSL:10m}
SSL_SESSION_TIMEOUT=${SSL_SESSION_TIMEOUT:-1d}

generate_nginx_config() {
  cat > /etc/nginx/nginx.conf <<EOF
worker_processes 1;
error_log /var/log/nginx/error.log warn;
pid /tmp/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;
    sendfile      on;
    keepalive_timeout 65;

EOF

  if [ -n "$HTTP_PORT" ]; then
    cat >> /etc/nginx/nginx.conf <<EOF
    server {
        listen ${HTTP_PORT};
        server_name localhost;
        root /var/www/html;
        index index.php index.html;

        access_log /var/www/html/logs/nginx-access.log;
        error_log /var/www/html/logs/nginx-error.log warn;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
        }

        location ~ /\.ht {
            deny all;
        }

        location ~ \.cache\.json$ {
            deny all;
        }
    }
EOF
  fi

  if [ -f "$SSL_CERT_PATH" ] && [ -f "$SSL_KEY_PATH" ]; then
    cat >> /etc/nginx/nginx.conf <<EOF
    server {
        listen ${HTTPS_PORT} ssl http2;
        server_name localhost;
        root /var/www/html;
        index index.php index.html;

        ssl_certificate ${SSL_CERT_PATH};
        ssl_certificate_key ${SSL_KEY_PATH};
        ssl_session_cache ${SSL_SESSION_CACHE};
        ssl_session_timeout ${SSL_SESSION_TIMEOUT};
        ssl_protocols ${SSL_PROTOCOLS};
        ssl_ciphers ${SSL_CIPHERS};
        ssl_prefer_server_ciphers on;
        add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php$ {
            include fastcgi_params;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
        }

        location ~ /\.ht {
            deny all;
        }

        location ~ \.cache\.json$ {
            deny all;
        }
    }
EOF
  fi
}

generate_nginx_config

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

if [ -n "$SYNC_CRON" ]; then
  cat > /etc/crontabs/root <<EOF
$SYNC_CRON /usr/local/bin/run-sync.sh >> /var/www/html/logs/cron.log 2>&1
EOF
  crond -l 8 &
fi

# Start PHP-FPM and Nginx first, then run the initial sync in the background.
php-fpm --nodaemonize 2>> /var/www/html/logs/php-fpm.log &
PHP_FPM_PID=$!

nginx -g 'daemon off;' &
NGINX_PID=$!

if [ ! -f /var/www/html/.initial_sync_done ]; then
  (
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running initial sync..." >> /var/www/html/logs/sync.log 2>&1
    /usr/local/bin/run-sync.sh >> /var/www/html/logs/sync.log 2>&1
    touch /var/www/html/.initial_sync_done
  ) &
fi

wait "$PHP_FPM_PID" "$NGINX_PID"
