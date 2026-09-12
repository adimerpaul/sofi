<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canasta observada.
 *
 * Revisar una canasta ahora termina de dos maneras: el caminero le da el visto
 * bueno, o la observa y escribe que paso (falto un producto, la canasta vino
 * cambiada). Las dos cierran la revision -el camion sale igual y caja puede
 * imprimir-, pero la observada llega marcada a facturacion para que caja la
 * resuelva.
 *
 * Por eso la bandera va aparte de verificado en vez de reemplazarlo: verificado
 * sigue queriendo decir "el caminero ya la reviso", que es lo que mira el
 * bloqueo de impresion, y observado dice como la cerro.
 *
 * No alcanza con mirar si observacion tiene texto: hasta ahora se podia anotar
 * algo en una canasta que estaba bien, y eso no es una observacion.
 */
class AddObservadoACargaVerificaciones extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('carga_verificaciones', 'observado')) {
            return;
        }

        Schema::table('carga_verificaciones', function (Blueprint $tabla) {
            $tabla->boolean('observado')->default(false)->after('verificado');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('carga_verificaciones', 'observado')) {
            return;
        }

        Schema::table('carga_verificaciones', function (Blueprint $tabla) {
            $tabla->dropColumn('observado');
        });
    }
}
