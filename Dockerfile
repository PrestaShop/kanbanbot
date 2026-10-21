FROM composer:2 as composer


COPY . /app
WORKDIR /app

RUN composer install

FROM php:8.4-apache

COPY etc/apache_vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer --chown=www-data:www-data /app /var/www/html

RUN a2enmod rewrite
