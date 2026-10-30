<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Las fotos de clientes en jpg/png (las importadas de sofiafactu pesan 4-6 MB
 * cada una) pasan a WebP comprimido. Las nuevas ya se guardan asi al subirlas.
 * Lo hace el comando fotos:comprimir-webp, que tambien se puede correr a mano.
 */
class ComprimirFotosClientesWebp extends Migration
{
    public function up()
    {
        Artisan::call('fotos:comprimir-webp');
        echo Artisan::output();
    }

    public function down()
    {
        // Los originales se borran al convertir: no hay vuelta atras.
    }
}
