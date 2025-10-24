FROM php:8.3-apache

# Gerekli Sistem Paketlerini Kurma (GD/ZIP/UTF8/SQLite)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    unzip \
    git \
    --no-install-recommends && rm -rf /var/lib/apt/lists/*

# PHP Eklentilerini Kurma
RUN docker-php-ext-install pdo pdo_sqlite gd zip

# Composer Kurulumu 
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache mod_rewrite aktif et
RUN a2enmod rewrite

#  Apache ayarını güncelleme /var/www/data dizinine web erişimini engelleme
RUN echo "<Directory /var/www/data>\nRequire all denied\n</Directory>" >> /etc/apache2/conf-available/data-security.conf && \
    a2enconf data-security

# Hata ayarları
RUN echo "display_errors=On\nerror_reporting=E_ALL" > /usr/local/etc/php/conf.d/dev.ini

WORKDIR /var/www/html