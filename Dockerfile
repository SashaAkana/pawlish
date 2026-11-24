FROM php:8.4-fpm-alpine AS php-base
WORKDIR /var/www/html
RUN apk add --no-cache bash curl icu-dev libzip-dev sqlite sqlite-dev su-exec unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath exif intl opcache pcntl pdo_sqlite zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor-builder
COPY src/ ./
# Package discovery may boot Laravel; use an isolated in-memory database at build time.
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && APP_ENV=production DB_DATABASE=:memory: composer install --no-dev --prefer-dist --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:22-alpine AS frontend-builder
WORKDIR /var/www/html
COPY src/package.json src/package-lock.json ./
RUN npm ci
COPY src/ ./
COPY --from=vendor-builder /var/www/html/vendor ./vendor
RUN npm run build

FROM php-base
COPY src/ ./
COPY --from=vendor-builder /var/www/html/vendor ./vendor
COPY --from=vendor-builder /var/www/html/public ./public
COPY --from=frontend-builder /var/www/html/public/build ./public/build
RUN cp -a public /opt/pawlish-public
COPY LICENSE NOTICE /usr/share/doc/pawlish/
COPY docker/php/entrypoint.sh /usr/local/bin/pawlish-entrypoint
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN chmod +x /usr/local/bin/pawlish-entrypoint
ENTRYPOINT ["pawlish-entrypoint"]
CMD ["php-fpm", "-F"]
