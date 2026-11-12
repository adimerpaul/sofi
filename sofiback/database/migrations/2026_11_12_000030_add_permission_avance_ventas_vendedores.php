<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permiso "Avance de ventas vendedores" (AvanceVentasController::miAvance): el
 * vendedor ve solo su avance del mes contra el objetivo que le pusieron. El
 * filtro lo hace el backend con el usuario logueado.
 *
 * Lo reciben los preventistas: el rol vendedor y quienes tienen el permiso
 * visita (como el Pareto de clientes).
 */
class AddPermissionAvanceVentasVendedores extends Migration
{
    private const PERMISO = 'avanceventasvendedores';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        $rol = Role::where('name', 'vendedor')->where('guard_name', 'web')->first();
        if ($rol && !$rol->hasPermissionTo($permiso)) {
            $rol->givePermissionTo($permiso);
        }

        if (Permission::where('name', 'visita')->where('guard_name', 'web')->exists()) {
            foreach (User::permission('visita')->get() as $usuario) {
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
