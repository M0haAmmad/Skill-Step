FROM php:8.2-apache

# تثبيت إضافات الداتابيز فقط بدون تفعيل موديولات Apache اللي بتعمل تعارض
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نسخ ملفات المشروع للمجلد الصحيح
COPY . /var/www/html/

# إعطاء الصلاحيات المناسبة للملفات
RUN chmod -R 755 /var/www/html/

EXPOSE 80
