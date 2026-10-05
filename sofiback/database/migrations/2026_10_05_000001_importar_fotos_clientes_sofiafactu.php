<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Trae a cliente_photos las fotos de clientes que se cargaron en sofiafactu.
 *
 * Lee la tabla clientes de la BD de sofiafactu (debe estar en el mismo servidor MySQL)
 * y baja cada imagen de https://bsofiafactu.tuprogam.com/uploads/clientes/...
 * Si esa BD no existe (por ejemplo en local) no hace nada; en ese caso se puede correr
 * a mano por la API: php artisan clientes:importar-fotos USUARIO
 */
class ImportarFotosClientesSofiafactu extends Migration
{
    private $bd = 'sofifactu';

    public function up()
    {
        $existe = DB::select(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'clientes'",
            [$this->bd]
        );
        if (!$existe) {
            echo "  BD {$this->bd} no encontrada: no se importaron fotos (usar php artisan clientes:importar-fotos USUARIO)\n";
            return;
        }

        Artisan::call('clientes:importar-fotos', ['--bd' => $this->bd]);
        echo Artisan::output();
    }

    public function down()
    {
        // Las fotos importadas quedan; se pueden borrar desde la pantalla de clientes.
    }
}
