<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El recojo del dia visto desde cobranzas: los mismos reportes que el caminero
 * saca de su camion, pero de todos.
 *
 * No va al rol despachador -el caminero solo tiene que ver lo suyo- sino a
 * quien recibe la plata al cerrar el dia.
 */
class AddPermissionCobranzasRecojo extends Migration
{
    private const ROLES = ['cobrador', 'encargado'];

    private const PERMISO = 'cobranzasrecojo';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');

        foreach (self::ROLES as $nombreRol) {
            $rol = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();
            if ($rol && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
