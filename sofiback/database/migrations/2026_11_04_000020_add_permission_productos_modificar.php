<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Productos queda con dos permisos:
 *   productos           -> ver la pantalla (listado, stock, entradas y salidas, Excel/PDF)
 *   productosModificar  -> crear, editar, eliminar/restaurar, imagenes, actualizar pollos, grupos y tipos
 *
 * productosModificar se da directo a los usuarios que hoy tienen productos,
 * no al rol vendedor. Se cambia luego desde Usuarios.
 */
class AddPermissionProductosModificar extends Migration
{
    private const PERMISO = 'productosModificar';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        $ver = Permission::where('name', 'productos')->where('guard_name', 'web')->first();
        app()['cache']->forget('spatie.permission.cache');

        // A los usuarios que hoy tienen productos (directo o por rol), pero no
        // al rol vendedor: los preventistas solo ven.
        if ($ver) {
            foreach (User::permission('productos')->get() as $usuario) {
                if (!$usuario->hasDirectPermission($permiso)) {
                    $usuario->givePermissionTo($permiso);
                }
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
