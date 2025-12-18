# Используем официальный образ PHP с Apache
FROM php:8.2-apache

# Устанавливаем расширения для PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Включаем модуль rewrite для Apache (если понадобится)
RUN a2enmod rewrite

# Копируем все файлы проекта в контейнер
COPY . /var/www/html/

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html

# Указываем рабочую директорию
WORKDIR /var/www/html