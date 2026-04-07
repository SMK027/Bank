FROM php:8.2-apache

# Dépendances système
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    default-mysql-client \
    zip \
    unzip \
    git \
    curl \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql \
    && a2enmod rewrite remoteip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Document root → public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configuration PHP
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Activer mod_remoteip : faire confiance au réseau interne Docker (nginx → Apache)
RUN echo 'RemoteIPHeader X-Forwarded-For\nRemoteIPTrustedProxy 172.16.0.0/12 10.0.0.0/8 192.168.0.0/16 127.0.0.1' \
    > /etc/apache2/conf-enabled/remoteip.conf

# Répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de l'application
COPY . .

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader 2>/dev/null || true

# Créer le dossier data
RUN mkdir -p data && chown -R www-data:www-data data && chmod -R 777 data

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/data

# Script d'entrée pour corriger les permissions des volumes
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Cron : transactions programmées
COPY docker/crontab /etc/cron.d/bankapp
RUN chmod 0644 /etc/cron.d/bankapp

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
