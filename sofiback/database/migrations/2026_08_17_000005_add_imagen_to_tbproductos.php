<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del producto.
 *
 * Va como columna nueva en tbproductos y no en una tabla aparte porque es un
 * dato uno-a-uno del producto. Es aditiva y nullable, asi que el sistema de
 * caja de escritorio, que lee tbproductos por nombre de columna, no se entera.
 *
 * Guarda la ruta relativa dentro de public/ (ej: uploads/productos/xxx.jpg),
 * igual que hace ClientePhotoController con las fotos de clientes.
 */
class AddImagenToTbproductos extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('tbproductos', 'imagen')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->string('imagen', 255)->nullable();
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('tbproductos', 'imagen')) {
            return;
        }

        Schema::table('tbproductos', function (Blueprint $table) {
            $table->dropColumn('imagen');
        });
    }
}
