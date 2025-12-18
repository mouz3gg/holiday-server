FROM php:8.2-apache

# Устанавливаем расширение PostgreSQL для PDO
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Включаем модуль rewrite для Apache
RUN a2enmod rewrite

# Копируем все файлы проекта в контейнер
COPY . /var/www/html/

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html

# Указываем рабочую директорию
WORKDIR /var/www/html

# Проверяем установку расширений
RUN php -r "if (!extension_loaded('pdo_pgsql')) { echo 'ERROR: pdo_pgsql not loaded!'; exit(1); } echo 'OK: pdo_pgsql loaded';"