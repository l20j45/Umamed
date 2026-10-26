<?php

namespace App\Support;

class Telefono
{
    /**
     * Deja el teléfono en el formato que espera WhatsApp: sólo dígitos y con
     * el código de país incluido (México antepone 52 a los números de 10).
     */
    public static function normalizar(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);

        if ($digitos === null || $digitos === '') {
            return null;
        }

        $pais = preg_replace('/\D+/', '', (string) config('whatsapp.pais', '52')) ?: '52';

        // Formato internacional marcado con 00 (0052...).
        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        // México usó "52 1" para celulares hasta 2019; se elimina ese 1.
        if (str_starts_with($digitos, $pais.'1') && strlen($digitos) === strlen($pais) + 11) {
            $digitos = $pais.substr($digitos, strlen($pais) + 1);
        }

        // Número local sin código de país.
        if (strlen($digitos) === 10) {
            $digitos = $pais.$digitos;
        }

        return $digitos;
    }

    /**
     * Presentación legible: +52 55 1234 5678.
     */
    public static function formatear(?string $telefono): ?string
    {
        $digitos = self::normalizar($telefono);

        if ($digitos === null) {
            return null;
        }

        $pais = preg_replace('/\D+/', '', (string) config('whatsapp.pais', '52')) ?: '52';

        if (! str_starts_with($digitos, $pais)) {
            return '+'.$digitos;
        }

        $local = substr($digitos, strlen($pais));

        if (strlen($local) === 10) {
            return '+'.$pais.' '.substr($local, 0, 2).' '.substr($local, 2, 4).' '.substr($local, 6, 4);
        }

        return '+'.$digitos;
    }
}
