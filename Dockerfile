FROM php:8.2-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        libxml2-dev \
        sqlite-dev \
    ; \
    apk add --no-cache \
        curl \
        git \
        unzip \
        libpng \
        libjpeg-turbo \
        freetype \
        libzip \
        libxml2 \
        oniguruma \
        sqlite-libs \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j$(nproc) \
        bcmath \
        ctype \
        dom \
        exif \
        fileinfo \
        gd \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        pdo_sqlite \
        session \
        sockets \
        xml \
        zip \
    ; \
    pecl install redis; \
    docker-php-ext-enable redis; \
    apk del .build-deps; \
    rm -rf /var/cache/apk/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

WORKDIR /var/www/html

RUN set -eux; \
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts; \
    php artisan package:discover --quiet || true; \
    rm -f bootstrap/cache/packages.php bootstrap/cache/services.php; \
    mkdir -p storage/app/public storage/logs storage/framework/cache/data \
             storage/framework/sessions storage/framework/views bootstrap/cache; \
    chown -R $UID:$GID storage bootstrap/cache; \
    chmod -R 775 storage bootstrap/cache

COPY infra/docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY infra/docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --chmod=+x infra/docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# php-fpm pool will switch to $UID:$GID for workers
RUN set -eux; \
    sed -i "s/^user = www-data/user = $UID/" /usr/local/etc/php-fpm.d/www.conf; \
    sed -i "s/^group = www-data/group = $GID/" /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
