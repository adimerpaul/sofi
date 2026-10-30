<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Guarda fotos en WebP reducidas: las del celular llegan de 3 a 6 MB y asi
 * quedan en unos cientos de KB sin que se note en pantalla.
 */
class FotoWebp
{
    const LADO_MAXIMO = 1600;
    const CALIDAD = 75;

    /**
     * Lee $origen (jpg, png, webp...), la encaja en $lado x $lado sin
     * deformarla ni ampliarla y la escribe en $destino como WebP.
     */
    public static function guardar(string $origen, string $destino, int $lado = self::LADO_MAXIMO, int $calidad = self::CALIDAD): void
    {
        $dir = dirname($destino);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $imagen = (new ImageManager(new Driver()))->read($origen);
        // scaleDown y no resizeDown: resizeDown fuerza el tamano exacto y deforma la foto.
        $imagen->scaleDown(width: $lado, height: $lado);
        $imagen->toWebp(quality: $calidad)->save($destino);
    }
}
