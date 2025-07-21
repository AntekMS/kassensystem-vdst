# Verwende das offizielle PHP-Image mit Apache
FROM php:8.2-apache

# Installiere System-Abhängigkeiten für PHP Extensions
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Installiere Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Konfiguriere GD Extension (für Bildbearbeitung)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Installiere notwendige PHP-Extensions für MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Installiere zusätzliche Extensions die CodeIgniter 4 braucht
RUN docker-php-ext-install mbstring xml curl gd zip intl

# Installiere OPcache für bessere Performance
RUN docker-php-ext-install opcache

# PHP-Performance Konfiguration
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Weitere PHP-Performance Einstellungen
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "max_input_vars=3000" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/performance.ini \
    && echo "upload_max_filesize=100M" >> /usr/local/etc/php/conf.d/performance.ini

# Aktiviere Apache mod_rewrite (wichtig für CodeIgniter URLs)
RUN a2enmod rewrite

# Kopiere deine Projektdateien in den Container
COPY . /var/www/html/

# Installiere Composer Dependencies (falls composer.json existiert)
RUN if [ -f /var/www/html/composer.json ]; then \
        cd /var/www/html && composer install --no-dev --optimize-autoloader; \
    fi

# Setze die richtigen Berechtigungen für CodeIgniter 4
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 755 /var/www/html/

# Spezielle Berechtigungen für CodeIgniter 4 writable Ordner
RUN chmod -R 777 /var/www/html/writable/

# Apache Konfiguration für CodeIgniter 4
RUN echo '<Directory /var/www/html/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
<Directory /var/www/html/public/>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/codeigniter.conf

RUN a2enconf codeigniter

# Setze DocumentRoot auf Hauptverzeichnis (falls kein public/ Ordner)
RUN sed -i 's|/var/www/html/public|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# Setze ServerName für korrekte Base URL Erkennung
RUN echo "ServerName localhost:8080" >> /etc/apache2/apache2.conf

# Exponiere Port 80 für den Webserver
EXPOSE 80