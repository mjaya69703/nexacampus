FROM php:8.3-fpm-bookworm AS app

WORKDIR /var/www/html

ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        git \
        gnupg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        zip \
        chromium \
        fonts-liberation \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/99-nexacampus.ini
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY .docker/php/entrypoint.sh /usr/local/bin/nexacampus-entrypoint

RUN chmod +x /usr/local/bin/nexacampus-entrypoint

ENTRYPOINT ["nexacampus-entrypoint"]
CMD ["php-fpm"]
