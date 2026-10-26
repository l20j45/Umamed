<?php

namespace App\Support;

use GdImage;

/**
 * Reescribe una foto de progreso como JPEG "limpio".
 *
 * - Elimina los metadatos EXIF (que incluyen GPS, fecha y modelo del teléfono).
 * - Corrige la orientación según el EXIF antes de descartarlo.
 * - Reduce el lado mayor a un máximo configurable para no guardar archivos de 12 MB.
 */
class OptimizadorDeFotos
{
    private int $ladoMaximo;

    private int $calidad;

    public function __construct()
    {
        $this->ladoMaximo = (int) config('clinic.foto_lado_maximo', 1600);
        $this->calidad = (int) config('clinic.foto_calidad_jpeg', 85);
    }

    /**
     * Procesa $origen y escribe el resultado en $destino (siempre JPEG).
     *
     * Devuelve false si la imagen no se pudo leer o escribir, para que el
     * llamador pueda decidir un respaldo en lugar de perder la foto.
     */
    public function procesar(string $origen, string $destino): bool
    {
        $info = @getimagesize($origen);

        if ($info === false) {
            return false;
        }

        $imagen = $this->abrir($origen, $info[2]);

        if (! $imagen instanceof GdImage) {
            return false;
        }

        $imagen = $this->orientar($imagen, $origen);
        $imagen = $this->escalar($imagen);

        // Lienzo blanco: los PNG/WebP pueden traer transparencia y el JPEG no.
        $lienzo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
        imagedestroy($imagen);

        $guardada = imagejpeg($lienzo, $destino, $this->calidad);
        imagedestroy($lienzo);

        return (bool) $guardada;
    }

    private function abrir(string $ruta, int $tipo): GdImage|false
    {
        return match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            default => false,
        };
    }

    private function orientar(GdImage $imagen, string $ruta): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        if (! $orientacion) {
            return $imagen;
        }

        $rotada = match ((int) $orientacion) {
            3 => imagerotate($imagen, 180, 0),
            6 => imagerotate($imagen, -90, 0),
            8 => imagerotate($imagen, 90, 0),
            default => false,
        };

        if (! $rotada instanceof GdImage) {
            // Las orientaciones 2, 4, 5 y 7 requieren espejo: se dejan intactas.
            return $imagen;
        }

        imagedestroy($imagen);

        return $rotada;
    }

    private function escalar(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $ladoMayor = max($ancho, $alto);

        if ($ladoMayor <= $this->ladoMaximo) {
            return $imagen;
        }

        $factor = $this->ladoMaximo / $ladoMayor;
        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto = max(1, (int) round($alto * $factor));

        $redimensionada = imagescale($imagen, $nuevoAncho, $nuevoAlto, IMG_BICUBIC);

        if (! $redimensionada instanceof GdImage) {
            return $imagen;
        }

        imagedestroy($imagen);

        return $redimensionada;
    }
}
