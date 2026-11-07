<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\PerfilActualizadoPush;

/**
 * Envía avisos push al paciente cuando la clínica actualiza su expediente.
 *
 * Sella tres condiciones para no disparar ruido: el canal push activo en la
 * configuración, el consentimiento del paciente (recibir_recordatorios) y que
 * tenga una suscripción viva del navegador.
 */
class NotificadorDePerfil
{
    public static function medición(User $paciente): void
    {
        self::enviar(
            $paciente,
            'Nueva medición registrada',
            'Tu peso e IMC se actualizaron en tu expediente.',
            '/mi-progreso',
        );
    }

    public static function nota(User $paciente, string $fecha, string $hora): void
    {
        self::enviar(
            $paciente,
            'Nueva nota en tu expediente',
            "La cita del {$fecha} a las {$hora} tiene notas de la clínica.",
            '/mis-citas',
        );
    }

    public static function recibo(User $paciente, string $fecha): void
    {
        self::enviar(
            $paciente,
            'Nuevo recibo en tu expediente',
            "Se generó un recibo de tu cita del {$fecha}.",
            '/mis-citas',
        );
    }

    /**
     * @param  ?User  $paciente  Puede venir vacío si la cita no tiene paciente.
     */
    public static function enviar(?User $paciente, string $titulo, string $cuerpo, string $url): void
    {
        $activos = (bool) config('whatsapp.recordatorios.push_activos', true);

        if (! $activos || ! $paciente || ! $paciente->recibir_recordatorios) {
            return;
        }

        if (! $paciente->pushSubscriptions()->exists()) {
            return;
        }

        $paciente->notify(new PerfilActualizadoPush($titulo, $cuerpo, $url));
    }
}
