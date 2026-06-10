#!/bin/bash
# deploy.sh

set -e

echo "🚀 Iniciando deploy..."

# Build de la imagen
docker compose build --no-cache

# Levantar MySQL primero y esperar que esté listo
docker compose up -d mysql
echo "⏳ Esperando MySQL..."
sleep 10

# Levantar la app
docker compose up -d Umamed

# Migraciones (esperando que el healthcheck de mysql pase)
echo "🗃️  Ejecutando migraciones..."
docker compose exec app php artisan migrate --force

# Cache de producción
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# Limpia jobs viejos si es re-deploy
docker compose exec app php artisan queue:restart  # señala a los workers que se reciclen


echo "✅ Deploy completado!"