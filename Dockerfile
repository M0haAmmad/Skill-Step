FROM php:8.2-apache
RUN a2enmod rewrite
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/
EXPOSE 80
RUN echo "upload_max_filesize = 1024M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 1024M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 1200" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 1200" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 1024M" >> /usr/local/etc/php/conf.d/uploads.ini
