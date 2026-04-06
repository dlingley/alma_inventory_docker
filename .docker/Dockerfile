FROM php:8.2-apache

LABEL maintainer="Dean Lingley"

WORKDIR /srv/app

COPY . /srv/app
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /srv/app \
    && a2enmod rewrite \
    && chmod 775 -R /srv/app/cache
