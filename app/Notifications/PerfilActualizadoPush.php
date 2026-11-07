<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso genérico de que la clínica registró algo nuevo en el expediente del
 * paciente (medición, notas de la cita, recibo en PDF…).
 */
class PerfilActualizadoPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $titulo, private string $cuerpo, private string $url) {}

    /**
     * @return list<string>
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->cuerpo)
            ->lang('es')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-192x192.png')
            ->data(['url' => $this->url]);
    }
}
