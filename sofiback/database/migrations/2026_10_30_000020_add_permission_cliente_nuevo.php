<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso "Nuevo Cliente": el alta paso a paso que reemplaza al formulario de
 * JotForm. El cliente queda pendiente de aprobacion de Ventas y Logistica.
 *
 * Lo reciben ya los preventistas: el rol vendedor y quienes tienen el permiso
 * visita (casi todos lo tienen directo, sin rol).
 */
class AddPermissionClienteNuevo extends Migration
{
    private const PERMISO = 'clienteNuevo';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        $rol = Role::where('name', 'vendedor')->where('guard_name', 'web')->first();
        if ($rol && !$rol->hasPermissionTo($permiso)) {
            $rol->givePermissionTo($permiso);
        }

        foreach (User::permission('visita')->get() as $usuario) {
            if (!$usuario->hasDirectPermission($permiso)) {
                $usuario->givePermissionTo($permiso);
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
