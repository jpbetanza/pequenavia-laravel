# syntax=docker/dockerfile:1

# Dependências PHP de produção (sem dev), com o código já presente para o autoload otimizado.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-req=ext-imagick
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction

# Dependências completas, usadas pelo estágio de testes e pelo Wayfinder (que precisa do artisan).
FROM composer:2 AS vendor-dev
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-req=ext-imagick
COPY . .
RUN composer dump-autoload --optimize --no-scripts --no-interaction

# Imagem base do PHP: FrankenPHP + extensões + Imagick (ImageMagick 7).
# PHP >= 8.5.12: corrige CVE-2026-109618 e CVE-2026-109619 (RCE, aviso de 2026-10-21).
FROM dunglas/frankenphp:1-php8.5.12-alpine AS base
RUN install-php-extensions imagick gd pdo_pgsql pcntl opcache intl zip gmp \
    && mkdir -p /config/caddy /data/caddy \
    && chown -R www-data:www-data /config /data
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
WORKDIR /app
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=pgsql
EXPOSE 8080

# Compila os assets com Node. O Wayfinder roda o artisan, então este estágio também tem PHP.
FROM base AS assets
RUN apk add --no-cache nodejs npm
COPY package.json package-lock.json ./
COPY . .
COPY --from=vendor-dev /app/vendor ./vendor
# npm ci, build e limpeza na mesma camada: node_modules (~700 MB) não entra na imagem de cache.
RUN npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules /root/.npm /root/.cache

FROM base AS runtime
# SHA do commit, vindo da CI: o site usa para avisar que há uma versão nova.
ARG APP_VERSION=
ENV APP_VERSION=${APP_VERSION}
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && rm -rf tests \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO /dev/null http://127.0.0.1:8080/up || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["web"]

FROM base AS test
COPY . .
COPY --from=vendor-dev /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
# Mesmo passo do `composer setup` da CI: chave de aplicação para os testes (só existe nesta imagem).
RUN cp .env.example .env && php artisan key:generate --ansi && php artisan package:discover --ansi
ENV APP_ENV=testing
CMD ["php", "artisan", "test", "--compact"]
