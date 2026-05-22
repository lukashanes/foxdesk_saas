FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        curl \
        libc-client2007e-dev \
        libkrb5-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo_mysql mysqli zip imap \
    && a2enmod rewrite headers remoteip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/foxdesk-prod.ini
COPY . /var/www/html

WORKDIR /var/www/html

RUN mkdir -p uploads storage/sessions backups \
    && chown -R www-data:www-data uploads storage backups \
    && find /var/www/html -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

EXPOSE 80
