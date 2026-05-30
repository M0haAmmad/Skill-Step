# استخدام نسخة Apache الرسمية المدعومة بالـ PHP
FROM php:8.2-apache

# تفعيل مود الـ Rewrite الخاص بملفات .htaccess المتواجدة بمشروعك
RUN a2enmod rewrite

# تثبيت إضافات الـ MySQL لربط قاعدة البيانات بنجاح
RUN docker-php-ext-install mysqli pdo pdo_mysql

# نقل كافة ملفات مشروعك إلى مجلد السيرفر الافتراضي
COPY . /var/www/html/

# السطر الصحيح لتعديل صلاحيات المجلد (القراءة والكتابة والتشغيل للـ Apache)
RUN chmod -R 755 /var/www/html/

EXPOSE 80