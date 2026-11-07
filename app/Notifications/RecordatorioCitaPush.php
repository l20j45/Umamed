<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class RecordatorioCitaPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Appointment $cita) {}

    /**
     * @return list<string>
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $inicio = $this->cita->inicioLocal();

        return (new WebPushMessage)
            ->title('Recordatorio de cita')
            ->body('Tienes cita el '.$inicio->format('d/m/Y').' a las '.$inicio->format('H:i'))
            ->lang('es')
            ->icon('/icons/icon-192x192.png')
            ->badge('/icons/icon-192x192.png')
            ->data(['url' => '/mis-citas']);
    }
}
