<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "Pareto de clientes (todos)": en el pareto de clientes se puede
 * elegir de que preventista verlo (sin el, cada uno ve solo lo suyo).
 *
 * Lo reciben ya quienes ven el avance de ventas de todos los vendedores
 * (permiso avanceventas).
 */
class AddPermissionParetoClientesTodos extends Migration
{
    private const PERMISO = 'paretoclientestodos';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        foreach (User::permission('avanceventas')->get() as $usuario) {
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
