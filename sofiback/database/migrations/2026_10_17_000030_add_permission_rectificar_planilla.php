<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "Rectificar planilla del dia": en Cobrar > Verificar facturacion,
 * reabrir un dia cuyo cierre ya se imprimio para volver a corregir lo
 * verificado. Sin el permiso el boton Rectificar no aparece. No se da a nadie
 * de entrada: se asigna desde Usuarios.
 */
class AddPermissionRectificarPlanilla extends Migration
{
    private const PERMISO = 'rectificarPlanilla';

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
