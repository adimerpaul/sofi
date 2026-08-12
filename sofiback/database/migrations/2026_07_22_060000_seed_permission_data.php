<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

class SeedPermissionData extends Migration
{
    /**
     * Ejecuta el PermissionSeeder al migrar, para que en el servidor
     * los permisos/roles se creen con un simple `php artisan migrate`.
     * El seeder es idempotente (firstOrCreate/sync), se puede re-ejecutar.
     */
    public function up()
    {
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\PermissionSeeder::class,
            '--force' => true,
        ]);
    }

    public function down()
    {
        // No se revierte: los permisos ya pueden haber sido modificados desde la app.
    }
}
