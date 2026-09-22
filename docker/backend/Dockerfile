FROM php:8.4-cli-alpine AS backend-base

RUN apk add --no-cache \
        icu-libs \
        libpq \
        libzip \
        su-exec \
    && apk add --no-cache --virtual .build-dependencies \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        linux-headers \
        postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-dependencies

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer
COPY docker/backend/verify-image-env.sh /usr/local/bin/verify-image-env

RUN chmod +x /usr/local/bin/verify-image-env

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock ./

FROM backend-base AS test

RUN composer install \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist

COPY backend/ ./

RUN verify-image-env \
    && composer dump-autoload --no-interaction --optimize \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

COPY docker/backend/entrypoint.sh /usr/local/bin/backend-entrypoint
COPY docker/backend/test-entrypoint.sh /usr/local/bin/backend-test-entrypoint

RUN chmod +x /usr/local/bin/backend-entrypoint /usr/local/bin/backend-test-entrypoint

ENTRYPOINT ["backend-test-entrypoint"]
CMD ["php", "artisan", "test", "--compact", "--do-not-cache-result"]

FROM backend-base AS production

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist

COPY backend/ ./

RUN verify-image-env \
    && composer dump-autoload --no-dev --no-interaction --optimize \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

COPY docker/backend/entrypoint.sh /usr/local/bin/backend-entrypoint

RUN chmod +x /usr/local/bin/backend-entrypoint

EXPOSE 8000 8080

ENTRYPOINT ["backend-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
