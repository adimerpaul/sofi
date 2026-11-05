<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "Verificación de facturas": subir el libro de ventas del SIAT y
 * compararlo con las facturas de Sofia por CUF (VerificacionFacturasController).
 * Sin el permiso no aparece el menu y la API responde 403. No se da a nadie
 * de entrada: se asigna desde Usuarios.
 */
class AddPermissionVerificacionFacturas extends Migration
{
    private const PERMISO = 'verificacionFacturas';

    public function up()
    {
        Permission::findOrCreate(self::PERMISO, 'web');

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
