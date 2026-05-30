FROM php:8.2-cli

# تثبيت إضافات الداتابيز
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نسخ ملفات المشروع بالكامل
COPY . /usr/src/myapp

# تحديد مجلد العمل داخل السيرفر
WORKDIR /usr/src/myapp

EXPOSE 80

# تشغيل السيرفر الداخلي لـ PHP مباشرة على بورت 80 بدون وجع راس Apache
CMD [ "php", "-S", "0.0.0.0:80" ]
