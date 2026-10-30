<?php

namespace App\Console\Commands;

use App\Models\ClientePhoto;
use App\Services\FotoWebp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa a WebP comprimido las fotos de clientes que todavia estan en jpg/png
 * (sobre todo las importadas de sofiafactu, que llegaron de 4 a 6 MB):
 * escribe el .webp al lado, actualiza cliente_photos.photo_path y borra el
 * original. Se puede correr de nuevo: solo toma las que no son .webp.
 *
 * Si el jpg ya no esta pero su .webp si (las fotos viajan en git: el pull
 * borra el jpg y trae el .webp, pero la base de ese servidor sigue con .jpg),
 * solo apunta la base al .webp. --reparar ademas registra las imagenes de
 * la carpeta que ninguna fila usa (fotos cambiadas en otra base).
 */
class ComprimirFotosClientes extends Command
{
    protected $signature = 'fotos:comprimir-webp
        {--simular : Solo cuenta lo que haria, sin tocar nada}
        {--reparar : Tambien registra las imagenes en disco que ninguna fila usa}';

    protected $description = 'Convierte a WebP comprimido las fotos de clientes que no lo son';

    public function handle()
    {
        // Una foto de 6000x4000 abierta con GD ocupa ~100 MB.
        @ini_set('memory_limit', '1024M');
        $simular = (bool) $this->option('simular');

        $fotos = ClientePhoto::withTrashed()->where('photo_path', 'not like', '%.webp')->orderBy('id')->get();
        $convertidas = 0;
        $reparadas = 0;
        $apuntadas = [];
        $sinArchivo = [];
        $errores = 0;
        $antes = 0;
        $despues = 0;

        foreach ($fotos as $foto) {
            $origen = public_path($foto->photo_path);
            $relativa = preg_replace('/\.[A-Za-z0-9]+$/', '', $foto->photo_path) . '.webp';
            $destino = public_path($relativa);

            if (!is_file($origen)) {
                // El .webp ya llego por otro lado (las fotos viajan en git y el
                // pull borra el jpg): solo falta apuntar la base al .webp.
                if (is_file($destino)) {
                    if (!$simular) {
                        $foto->photo_path = $relativa;
                        $foto->save();
                    }
                    $apuntadas[] = $relativa;
                    $reparadas++;
                } else {
                    $sinArchivo[] = $foto;
                }
                continue;
            }

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

        $adoptadas = 0;
        $reemplazadas = 0;
        if ($this->option('reparar')) {
            [$adoptadas, $reemplazadas] = $this->adoptarHuerfanas($sinArchivo, $apuntadas, $simular);
        }

        $mb = function ($bytes) { return number_format($bytes / 1048576, 1) . ' MB'; };
        if ($reparadas) $this->info(($simular ? 'Se apuntarian' : 'Apuntadas') . " al .webp que ya estaba en disco: {$reparadas}");
        if ($adoptadas) $this->info(($simular ? 'Se recuperarian' : 'Recuperadas') . " fotos que estaban en disco sin registro: {$adoptadas}");
        if ($reemplazadas) $this->info(($simular ? 'Se darian' : 'Dadas') . " de baja por estar reemplazadas y sin archivo: {$reemplazadas}");
        $sinArchivo = count($sinArchivo) - $reemplazadas;
        if ($simular) {
            $this->info("Se convertirian {$convertidas} fotos ({$mb($antes)})");
        } else {
            $this->info("Fotos convertidas a WebP: {$convertidas}   {$mb($antes)} -> {$mb($despues)}");
        }
        if ($sinArchivo) $this->warn("Registros sin archivo en disco: {$sinArchivo}");
        if ($errores) $this->warn("No se pudieron convertir: {$errores}");

        return 0;
    }

    /**
     * Clientes con una foto rota (fila activa sin archivo): pasa cuando la
     * foto se cambio en otra base y el archivo nuevo llego por git con otro
     * nombre. Las imagenes de su carpeta que ninguna fila usa (ni borrada) se
     * registran como su foto, y la fila rota se da de baja (borrado logico, se
     * puede restaurar). Solo esos clientes: el codigo de un cliente de prueba
     * de otra base puede ser aca otro cliente, y no se le agrega nada.
     */
    private function adoptarHuerfanas(array $sinArchivo, array $apuntadas, bool $simular): array
    {
        // $apuntadas: las que recien se apuntaron al .webp (al simular no se guardaron).
        $usadas = ClientePhoto::withTrashed()->pluck('photo_path')->merge($apuntadas)
            ->map(function ($p) { return ltrim(str_replace('\\', '/', $p), '/'); })->flip();
        $rotos = collect($sinArchivo)->reject(function ($f) { return $f->trashed(); })
            ->pluck('cliente_id')->map(function ($c) { return (int) $c; })->unique();
        $adoptadas = 0;
        $clientesConNueva = [];

        foreach ($rotos as $cliente) {
            $dir = public_path("cliente_photos/{$cliente}");
            if (!is_dir($dir) || !DB::table('tbclientes')->where('Cod_Aut', $cliente)->exists()) continue;

            foreach (glob($dir . '/*') as $archivo) {
                if (!is_file($archivo) || !preg_match('/\.(webp|jpe?g|png)$/i', $archivo)) continue;
                $relativa = "cliente_photos/{$cliente}/" . basename($archivo);
                if (isset($usadas[$relativa])) continue;

                if (!$simular) {
                    if (!preg_match('/\.webp$/i', $archivo)) {
                        $webp = preg_replace('/\.[A-Za-z0-9]+$/', '', $relativa) . '.webp';
                        try {
                            FotoWebp::guardar($archivo, public_path($webp));
                        } catch (\Throwable $e) {
                            $this->warn("No se pudo convertir {$relativa}: " . $e->getMessage());
                            continue;
                        }
                        @unlink($archivo);
                        $relativa = $webp;
                    }
                    ClientePhoto::create(['cliente_id' => (int) $cliente, 'photo_path' => $relativa]);
                }
                $this->line("  recuperada: {$relativa}");
                $adoptadas++;
                $clientesConNueva[(int) $cliente] = true;
            }
        }

        $reemplazadas = 0;
        foreach ($sinArchivo as $foto) {
            if ($foto->trashed() || !isset($clientesConNueva[(int) $foto->cliente_id])) continue;
            if (!$simular) $foto->delete();
            $this->line("  dada de baja (sin archivo, reemplazada): {$foto->photo_path}");
            $reemplazadas++;
        }

        return [$adoptadas, $reemplazadas];
    }
}
