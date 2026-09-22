# Development image: PHP 8.3 with the extensions Coldwire needs.
FROM php:8.3-cli

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql gd; \
    rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/coldwire.ini

WORKDIR /app
EXPOSE 8080
