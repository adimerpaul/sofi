<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Repara las fotos de clientes en un servidor cuya base quedo apuntando a los
 * .jpg que el git pull borro (la conversion a WebP se hizo en otra base y los
 * .webp llegaron por git). Apunta cada fila a su .webp, convierte los jpg que
 * queden, registra las imagenes de la carpeta que ninguna fila usa y da de
 * baja (borrado logico) las filas sin archivo de esos clientes.
 * Si ya esta todo bien, no hace nada.
 */
class RepararRutasFotosClientes extends Migration
{
    public function up()
    {
        Artisan::call('fotos:comprimir-webp', ['--reparar' => true]);
        echo Artisan::output();
    }

    public function down()
    {
        // Solo corrige rutas y registra archivos que ya estaban: no hay nada que deshacer.
    }
}
