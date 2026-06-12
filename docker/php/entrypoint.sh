#!/bin/sh
# 바인드 마운트시 storage 쓰기 권한 보장
set -e

if [ -d /var/www/html/storage ]; then
    chmod -R ugo+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
