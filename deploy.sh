#!/bin/bash
# deploy.sh

set -e

# El servicio en docker-compose.yml se llama "umamed".
SERVICE="umamed"

echo "🚀 Iniciando deploy..."

# Build de la imagen
docker compose build --no-cache

# Levantar la app (la base SQLite vive en el volumen de storage)
docker compose up -d "$SERVICE"

# Migraciones
echo "🗃️  Ejecutando migraciones..."
docker compose exec "$SERVICE" php artisan migrate --force

# Cachés de producción (config primero, luego rutas y vistas)
docker compose exec "$SERVICE" php artisan optimize:clear
docker compose exec "$SERVICE" php artisan config:cache
docker compose exec "$SERVICE" php artisan route:cache
docker compose exec "$SERVICE" php artisan view:cache

# Recicla los workers de la cola tras el deploy
docker compose exec "$SERVICE" php artisan queue:restart

echo "✅ Deploy completado!"
