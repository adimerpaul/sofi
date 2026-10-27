<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "Facturación ficticia": ver, emitir al SIAT, anular e imprimir
 * facturas de facturas_ficticias. Sin el permiso no aparece el menu y la API
 * responde 403. No se da a nadie de entrada: se asigna desde Usuarios.
 */
class AddPermissionFacturacionFicticia extends Migration
{
    private const PERMISO = 'facturacionFicticia';

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
