FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends -y \
        curl \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpq-dev \
        libwebp-dev \
        libxpm-dev \
        libxslt1-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        sockets \
        xsl \
        zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

COPY docker/application/apache.conf /etc/apache2/conf-available/mundial.conf
RUN a2enconf mundial \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data src ./src
