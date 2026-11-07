FROM php:8.5-fpm-alpine AS base

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
    sqlite-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        mbstring \
        zip \
        gd \
        pcntl \
        bcmath

# El directorio de logs de supervisor no se crea solo en Alpine y supervisord
# aborta sin él (ver supervisord.conf: logfile=/var/log/supervisor/…).
RUN mkdir -p /var/log/supervisor

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- Stage de producción ---
FROM base AS production

# Copia todo el proyecto de Laravel (incluyendo composer.json, supervisord.conf y default.conf)
COPY . .

# Asegurar carpetas clave y el archivo de la base SQLite
RUN mkdir -p storage bootstrap/cache && touch storage/app/umamed.sqlite

# Instalación de dependencias (pnpm: el repo ya no usa npm/package-lock)
RUN npm i -g pnpm@12.16.0 \
    && composer install --no-dev --optimize-autoloader --no-interaction \
    && pnpm install --frozen-lockfile \
    && pnpm run build \
    && rm -rf node_modules

# Permisos correctos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# MODIFICADO: Como los archivos ya están en la raíz, los copiamos desde el directorio actual (.)
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./default.conf /etc/nginx/http.d/default.conf

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
