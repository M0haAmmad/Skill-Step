FROM php:8.2-apache
RUN a2enmod rewrite
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

RUN cp /var/www/html/Login/login.php /var/www/html/index.php

RUN chmod -R 755 /var/www/html/
EXPOSE 80
