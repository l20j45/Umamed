# deploy.ps1

$ErrorActionPreference = "Stop"

# El servicio en docker-compose.yml se llama "umamed".
$Service = "umamed"

Write-Host "🚀 Iniciando deploy local..." -ForegroundColor Cyan

podman compose build --no-cache

podman compose up -d $Service

Write-Host "🗃️  Ejecutando migraciones..." -ForegroundColor Cyan
podman compose exec $Service php artisan migrate --force

podman compose exec $Service php artisan optimize:clear
podman compose exec $Service php artisan config:cache
podman compose exec $Service php artisan route:cache
podman compose exec $Service php artisan view:cache

podman compose exec $Service php artisan queue:restart

Write-Host "✅ Deploy completado!" -ForegroundColor Green
