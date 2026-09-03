<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las dos pantallas del caminero: sus entregas del dia (cobrar) y su reporte
 * de recojo de dinero. Van al rol despachador, que es quien sale con el
 * camion, y a encargado para que administracion pueda revisarlas.
 */
class AddPermissionCaminero extends Migration
{
    private const ROLES = ['despachador', 'encargado'];

    private const PERMISOS = ['misentregas', 'misentregasreporte'];

    public function up()
    {
        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::findOrCreate($nombre, 'web');

            foreach (self::ROLES as $nombreRol) {
                $rol = Role::where('name', $nombreRol)->where('guard_name', 'web')->first();
                if ($rol && !$rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
