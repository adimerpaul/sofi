<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Permiso para administrar proveedores, con el mismo criterio que compras. */
class AddPermissionProveedores extends Migration
{
    private const ROL = 'encargado';

    private const PERMISO = 'proveedores';

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
