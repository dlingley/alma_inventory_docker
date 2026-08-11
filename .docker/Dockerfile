FROM php:8.2-apache

LABEL maintainer="Dean Lingley"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /srv/app

RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock /srv/app/
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . /srv/app
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /srv/app \
    && a2enmod rewrite \
    && chmod 775 -R /srv/app/cache

