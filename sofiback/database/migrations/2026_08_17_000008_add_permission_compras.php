<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos del modulo de compras, con el mismo criterio que facturacion: el
 * nombre del permiso coincide con la ruta del menu, y se asignan al rol que ya
 * administra ventas y productos.
 */
class AddPermissionCompras extends Migration
{
    private const ROL = 'encargado';

    private const PERMISOS = ['compras', 'comprasNueva', 'comprasAnular'];

    public function up()
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if ($rol) {
            foreach (self::PERMISOS as $nombre) {
                $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
                if ($permiso && !$rol->hasPermissionTo($permiso)) {
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
