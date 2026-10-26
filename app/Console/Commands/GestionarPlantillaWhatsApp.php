<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GestionarPlantillaWhatsApp extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:plantilla
                            {--crear : Envía la plantilla a Meta para aprobación}
                            {--nombre= : Nombre de la plantilla (por defecto la configurada)}
                            {--idioma= : Código de idioma (por defecto el configurado)}';

    /**
     * @var string
     */
    protected $description = 'Consulta o envía a aprobación la plantilla de recordatorios de WhatsApp';

    public function handle(WhatsAppService $whatsapp): int
    {
        if ($whatsapp->modo() !== 'cloud') {
            $this->components->error(
                'Falta configurar WhatsApp: revisa WHATSAPP_DRIVER=cloud, WHATSAPP_TOKEN y WHATSAPP_PHONE_NUMBER_ID.'
            );

            return self::FAILURE;
        }

        $waba = (string) config('whatsapp.cloud.waba_id');

        if (blank($waba)) {
            $this->components->error('Falta WHATSAPP_WABA_ID (WhatsApp Business Account ID).');

            return self::FAILURE;
        }

        $nombre = (string) ($this->option('nombre') ?: config('whatsapp.cloud.plantilla.nombre'));
        $idioma = (string) ($this->option('idioma') ?: config('whatsapp.cloud.plantilla.idioma'));

        if (blank($nombre)) {
            $this->components->error('Falta WHATSAPP_PLANTILLA o el parámetro --nombre.');

            return self::FAILURE;
        }

        return $this->option('crear')
            ? $this->crear($waba, $nombre, $idioma)
            : $this->consultar($waba, $nombre);
    }

    private function crear(string $waba, string $nombre, string $idioma): int
    {
        $cuerpo = (string) config('whatsapp.cloud.plantilla.cuerpo');
        $ejemplo = array_values((array) config('whatsapp.cloud.plantilla.ejemplo'));

        $variables = preg_match_all('/\{\{\d+\}\}/', $cuerpo);

        if ($variables !== 3) {
            $this->components->error(
                "El cuerpo de la plantilla debe tener exactamente 3 variables {{1}}, {{2}} y {{3}}; tiene {$variables}."
            );

            return self::FAILURE;
        }

        $respuesta = Http::withToken((string) config('whatsapp.cloud.token'))
            ->acceptJson()
            ->timeout(20)
            ->post($this->url($waba, 'message_templates'), [
                'name' => $nombre,
                'language' => $idioma,
                'category' => (string) config('whatsapp.cloud.plantilla.categoria', 'UTILITY'),
                'components' => [
                    [
                        'type' => 'BODY',
                        'text' => $cuerpo,
                        'example' => ['body_text' => [$ejemplo]],
                    ],
                ],
            ]);

        if ($respuesta->failed()) {
            $this->components->error('Meta rechazó la solicitud ('.$respuesta->status().'):');
            $this->line($respuesta->body());

            return self::FAILURE;
        }

        $this->components->info(
            'Plantilla enviada. Estado: '.($respuesta->json('status') ?? 'PENDING')
            .' · id: '.($respuesta->json('id') ?? '—')
        );
        $this->line('Meta suele tardar unos minutos en revisarla. Consulta el avance con:');
        $this->line('  php artisan whatsapp:plantilla');

        return self::SUCCESS;
    }

    private function consultar(string $waba, string $nombre): int
    {
        $respuesta = Http::withToken((string) config('whatsapp.cloud.token'))
            ->acceptJson()
            ->timeout(20)
            ->get($this->url($waba, 'message_templates'), [
                'name' => $nombre,
                'fields' => 'name,status,language,category,rejected_reason',
            ]);

        if ($respuesta->failed()) {
            $this->components->error('No se pudo consultar Meta ('.$respuesta->status().'):');
            $this->line($respuesta->body());

            return self::FAILURE;
        }

        $plantillas = (array) $respuesta->json('data', []);

        if ($plantillas === []) {
            $this->components->warn("La plantilla [{$nombre}] no existe todavía en esa cuenta.");
            $this->line('Créala con: php artisan whatsapp:plantilla --crear');

            return self::SUCCESS;
        }

        $this->table(
            ['Nombre', 'Idioma', 'Categoría', 'Estado', 'Motivo del rechazo'],
            array_map(static fn (array $plantilla): array => [
                $plantilla['name'] ?? '—',
                $plantilla['language'] ?? '—',
                $plantilla['category'] ?? '—',
                $plantilla['status'] ?? '—',
                $plantilla['rejected_reason'] ?? '—',
            ], $plantillas),
        );

        return self::SUCCESS;
    }

    private function url(string $waba, string $recurso): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/%s',
            config('whatsapp.cloud.version'),
            $waba,
            $recurso,
        );
    }
}
