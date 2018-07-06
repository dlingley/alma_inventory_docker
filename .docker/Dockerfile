FROM php:5.6.36-apache

MAINTAINER Dean Lingley

COPY . /srv/app
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /srv/app \
    && a2enmod rewrite
RUN chmod a+rwx -R /srv/app/cache
