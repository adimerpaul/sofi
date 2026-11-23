<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso "almacenRiesgo": menu Almacen > Productos en riesgo (ver, generar y quitar).
 *
 * Lo reciben todos los que hoy tienen el permiso "almacen": los roles que lo
 * tienen y los usuarios que lo tienen directo.
 */
class AddPermissionAlmacenRiesgo extends Migration
{
    private const PERMISO = 'almacenRiesgo';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        $almacen = Permission::where('name', 'almacen')->where('guard_name', 'web')->first();
        if ($almacen) {
            foreach (Role::whereHas('permissions', function ($q) use ($almacen) {
                $q->where('id', $almacen->id);
            })->get() as $rol) {
                if (!$rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
            foreach (User::permission('almacen')->get() as $usuario) {
                // Los que lo tienen por rol ya lo reciben con el rol.
                if ($usuario->hasDirectPermission($almacen) && !$usuario->hasDirectPermission($permiso)) {
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
