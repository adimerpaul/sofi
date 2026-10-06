<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso de la opcion "Actualizar precios" del menu Cobros: ver y cambiar
 * los 13 precios y el precio de compra de todos los productos. Se da al rol
 * encargado; a otros roles se les asigna desde la pantalla de Usuarios.
 */
class AddPermissionPreciosActualizar extends Migration
{
    private const PERMISO = 'preciosActualizar';

    private const ROL = 'encargado';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');

        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if ($rol && !$rol->hasPermissionTo($permiso)) {
            $rol->givePermissionTo($permiso);
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
