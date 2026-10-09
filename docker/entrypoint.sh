#!/bin/sh
set -e

cd /app

papel="${1:-web}"

case "$papel" in
  web)
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
    ;;
  queue)
    php artisan config:cache
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    ;;
  *)
    exec "$@"
    ;;
esac
