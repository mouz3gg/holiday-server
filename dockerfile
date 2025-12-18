FROM php:8.2-apache

# Устанавливаем системные зависимости
RUN apt-get update && apt-get install -y \
    libpq-dev \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Устанавливаем расширение PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql

# Включаем модуль Apache
RUN a2enmod rewrite

# Копируем файлы
COPY . /var/www/html/

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html

# Рабочая директория
WORKDIR /var/www/html

# Проверяем установленные расширения
RUN php -m | grep -i pdo
RUN php -m | grep -i pgsql