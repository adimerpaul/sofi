<?php

namespace App\Console\Commands;

use App\Models\ClientePhoto;
use App\Services\FotoWebp;
use Illuminate\Console\Command;

/**
 * Pasa a WebP comprimido las fotos de clientes que todavia estan en jpg/png
 * (sobre todo las importadas de sofiafactu, que llegaron de 4 a 6 MB):
 * escribe el .webp al lado, actualiza cliente_photos.photo_path y borra el
 * original. Se puede correr de nuevo: solo toma las que no son .webp.
 */
class ComprimirFotosClientes extends Command
{
    protected $signature = 'fotos:comprimir-webp {--simular : Solo cuenta lo que convertiria, sin tocar nada}';

    protected $description = 'Convierte a WebP comprimido las fotos de clientes que no lo son';

    public function handle()
    {
        // Una foto de 6000x4000 abierta con GD ocupa ~100 MB.
        @ini_set('memory_limit', '1024M');
        $simular = (bool) $this->option('simular');

        $fotos = ClientePhoto::withTrashed()->where('photo_path', 'not like', '%.webp')->orderBy('id')->get();
        $convertidas = 0;
        $sinArchivo = 0;
        $errores = 0;
        $antes = 0;
        $despues = 0;

        foreach ($fotos as $foto) {
            $origen = public_path($foto->photo_path);
            if (!is_file($origen)) {
                $sinArchivo++;
                continue;
            }

            $relativa = preg_replace('/\.[A-Za-z0-9]+$/', '', $foto->photo_path) . '.webp';
            $destino = public_path($relativa);
            $peso = filesize($origen);

            if ($simular) {
                $antes += $peso;
                $convertidas++;
                continue;
            }

            try {
                FotoWebp::guardar($origen, $destino);
            } catch (\Throwable $e) {
                $this->warn("No se pudo convertir {$foto->photo_path}: " . $e->getMessage());
                $errores++;
                continue;
            }

            $antes += $peso;
            $despues += filesize($destino);
            $foto->photo_path = $relativa;
            $foto->save();
            @unlink($origen);
            $convertidas++;
        }

        $mb = function ($bytes) { return number_format($bytes / 1048576, 1) . ' MB'; };
        if ($simular) {
            $this->info("Se convertirian {$convertidas} fotos ({$mb($antes)})");
        } else {
            $this->info("Fotos convertidas a WebP: {$convertidas}   {$mb($antes)} -> {$mb($despues)}");
        }
        if ($sinArchivo) $this->warn("Registros sin archivo en disco: {$sinArchivo}");
        if ($errores) $this->warn("No se pudieron convertir: {$errores}");

        return 0;
    }
}
