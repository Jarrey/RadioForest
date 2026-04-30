FROM php:8.2-fpm-alpine3.20

ARG TZ=Asia/Shanghai
ENV TZ=${TZ}
RUN apk add --no-cache nginx python3 py3-requests tzdata \
 && mkdir -p /var/www/html /var/www/html/logs

COPY docker/start.sh /usr/local/bin/start.sh
COPY docker/sync.sh /usr/local/bin/sync.sh
COPY docker/.env.sample /var/www/html/.env.sample
COPY index.php /var/www/html/
COPY radio-icon.svg /var/www/html/
COPY scripts/radioBrowserService.py /var/www/html/scripts/
COPY scripts/syncInternetRatio.py /var/www/html/scripts/
COPY lang /var/www/html/lang/

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/start.sh /usr/local/bin/sync.sh

EXPOSE 18882 18883

CMD ["/usr/local/bin/start.sh"]
