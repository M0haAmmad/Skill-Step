FROM php:8.2-apache-bullseye

# تنظيف وتحديث الموديولات لمنع أي تعارض في الـ MPM
RUN apt-get update && apt-get install -y \
    && a2dismod mpm_event || true \
    && a2enmod mpm_prefork || true

# تثبيت إضافات الداتابيز
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نسخ الملفات وإعطاء الصلاحيات
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/

EXPOSE 80
