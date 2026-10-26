FROM php:8.3-fpm-alpine AS base

# Dependencias del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    zip unzip \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        pcntl \
        bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Stage de producción ---
FROM base AS production

# Copia todo el proyecto de Laravel (incluyendo composer.json, supervisord.conf y default.conf)
COPY . .

# Asegurar carpetas clave y el archivo de la base SQLite
RUN mkdir -p storage bootstrap/cache && touch storage/app/umamed.sqlite

# Instalación de dependencias
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci && npm run build \
    && rm -rf node_modules

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# MODIFICADO: Como los archivos ya están en la raíz, los copiamos desde el directorio actual (.)
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./default.conf /etc/nginx/http.d/default.conf

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]