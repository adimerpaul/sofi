<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos de Transformacion de productos (pestaña en Reportes de Ventas y
 * Facturacion):
 *   transformacionVer    -> ver el listado y el detalle
 *   transformacionCrear  -> registrar una transformacion (mueve stock)
 *   transformacionAnular -> anularla (devuelve el stock)
 * Se dan al rol encargado; a otros, desde la pantalla de Usuarios.
 */
class AddPermissionTransformaciones extends Migration
{
    private const PERMISOS = ['transformacionVer', 'transformacionCrear', 'transformacionAnular'];

    private const ROL = 'encargado';

    public function up()
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();

        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::findOrCreate($nombre, 'web');
            if ($rol && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
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
