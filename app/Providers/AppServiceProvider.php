<?php

namespace App\Providers;

use App\Listeners\NotificarJobFallido;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // El visor de logs viene abierto a cualquiera por defecto y muestra
        // trazas con datos de pacientes: se restringe a los administradores.
        LogViewer::auth(
            fn (Request $request): bool => (bool) $request->user()?->hasRole('admin')
        );

        // Avisa por correo cuando un job agota sus reintentos (por ejemplo un
        // recordatorio de WhatsApp que Meta rechaza).
        Event::listen(JobFailed::class, NotificarJobFallido::class);

        // Fuerza el uso de HTTPS si la petición viene a través del túnel de Ngrok.
        if ($this->app->environment('local') && request()->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            URL::forceScheme('https');
        }
    }
}
