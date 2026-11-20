# Aviate API: one image used for three containers (api, queue, reverb).
FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql gd exif pcntl opcache zip \
 && apk add --no-cache su-exec

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-aviate.ini

WORKDIR /var/www/html

# Install dependencies first so Docker can cache this layer.
# Set COMPOSER_NO_DEV=1 for production builds (skips PHPUnit etc.).
ARG COMPOSER_NO_DEV=0
COPY composer.json composer.lock* ./
RUN if [ "$COMPOSER_NO_DEV" = "1" ]; then NODEV=--no-dev; fi; \
    composer install --no-interaction --no-scripts --no-autoloader --prefer-dist $NODEV

COPY . .
RUN if [ "$COMPOSER_NO_DEV" = "1" ]; then NODEV=--no-dev; fi; \
    composer dump-autoload --optimize $NODEV \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/aviate-entrypoint
ENTRYPOINT ["aviate-entrypoint"]
CMD ["php-fpm"]
