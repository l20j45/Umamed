<?php

namespace App\Services;

use App\Support\Telefono;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class WhatsAppService
{
    /**
     * Modo efectivo de envío: "cloud" sólo si hay credenciales configuradas.
     */
    public function modo(): string
    {
        return $this->configurado() ? 'cloud' : 'log';
    }

    public function configurado(): bool
    {
        return (string) config('whatsapp.driver') === 'cloud'
            && filled(config('whatsapp.cloud.token'))
            && filled(config('whatsapp.cloud.phone_number_id'));
    }

    /**
     * ¿Hay una plantilla aprobada configurada?
     *
     * Meta sólo admite texto libre dentro de las 24 h siguientes al último
     * mensaje del paciente; fuera de esa ventana hay que usar plantilla.
     */
    public function plantillaConfigurada(): bool
    {
        return filled(config('whatsapp.cloud.plantilla.nombre'));
    }

    /**
     * Envía un mensaje de texto o, si hay plantilla y se reciben parámetros,
     * usa la plantilla aprobada (necesario para mensajes iniciados por el
     * negocio, como los recordatorios de cita).
     *
     * @param  list<string>|null  $parametrosPlantilla
     *
     * @throws InvalidArgumentException cuando el teléfono no es utilizable.
     */
    public function enviar(?string $telefono, string $mensaje, ?array $parametrosPlantilla = null): void
    {
        $destino = Telefono::normalizar($telefono);

        if ($destino === null) {
            throw new InvalidArgumentException('El paciente no tiene un teléfono válido.');
        }

        if ($this->modo() === 'cloud') {
            if ($parametrosPlantilla !== null && $this->plantillaConfigurada()) {
                $this->enviarPorPlantilla($destino, $parametrosPlantilla);

                return;
            }

            $this->enviarPorCloudApi($destino, $mensaje);

            return;
        }

        // Modo desarrollo: el mensaje no sale, pero queda registro de lo enviado.
        Log::info('WhatsApp (modo log)', [
            'para' => $destino,
            'mensaje' => $mensaje,
            'plantilla' => $parametrosPlantilla,
        ]);
    }

    /**
     * Enlace wa.me con el mensaje ya escrito, para enviarlo a mano.
     */
    public function enlace(?string $telefono, string $mensaje): ?string
    {
        $destino = Telefono::normalizar($telefono);

        if ($destino === null) {
            return null;
        }

        return 'https://wa.me/'.$destino.'?text='.rawurlencode($mensaje);
    }

    private function enviarPorCloudApi(string $destino, string $mensaje): void
    {
        $this->postearCloudApi([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $destino,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $mensaje,
            ],
        ]);
    }

    /**
     * @param  list<string>  $parametros
     */
    private function enviarPorPlantilla(string $destino, array $parametros): void
    {
        $this->postearCloudApi([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $destino,
            'type' => 'template',
            'template' => [
                'name' => (string) config('whatsapp.cloud.plantilla.nombre'),
                'language' => [
                    'code' => (string) config('whatsapp.cloud.plantilla.idioma', 'es_MX'),
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(
                            static fn (string $texto): array => ['type' => 'text', 'text' => $texto],
                            array_values($parametros),
                        ),
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postearCloudApi(array $payload): void
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('whatsapp.cloud.version'),
            config('whatsapp.cloud.phone_number_id'),
        );

        Http::withToken((string) config('whatsapp.cloud.token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 500)
            ->post($url, $payload)
            ->throw();
    }
}
