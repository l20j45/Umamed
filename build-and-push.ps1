# build-and-push.ps1

$ErrorActionPreference = "Stop"

# Los nombres de imagen en Docker deben ir en minúsculas.
$imageName = "l20j45/umamed"
$tag = "latest"

Write-Host "📦 Building imagen para ARM64..." -ForegroundColor Cyan

podman build `
    --platform linux/arm64 `
    -t "${imageName}:${tag}" `
    -f Dockerfile .

Write-Host "🔐 Login al registry..." -ForegroundColor Cyan
podman login docker.io

Write-Host "⬆️  Pusheando imagen..." -ForegroundColor Cyan
podman push "${imageName}:${tag}"

Write-Host "✅ Listo!" -ForegroundColor Green
