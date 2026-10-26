<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;

class SendWelcomeNotification
{
    /**
     * Envía al paciente sus credenciales de acceso.
     *
     * Se envía en línea (no en cola) a propósito: así la contraseña en texto
     * plano nunca queda guardada en la tabla `jobs` al serializar el listener.
     */
    public function handle(UserCreated $event): void
    {
        Mail::to($event->user->email)->send(
            new WelcomeUserMail($event->user, $event->password)
        );
    }
}
