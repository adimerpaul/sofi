<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Caja aprueba de una vez la carga de un camion desde Facturacion, para no
 * esperar a que el caminero revise canasta por canasta.
 *
 * Arranca con quien hoy entra a Facturacion, por el mismo camino por el que lo
 * tiene: el rol o el permiso directo.
 */
class AddPermissionFacturacionAprobarCarga extends Migration
{
    private const PERMISO = 'facturacionAprobarCarga';

    private const BASE = 'facturacion';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');

        Role::where('guard_name', 'web')->get()->each(function ($rol) use ($permiso) {
            if ($rol->hasPermissionTo(self::BASE) && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        });

        User::permission(self::BASE)->get()->each(function ($usuario) use ($permiso) {
            if ($usuario->hasDirectPermission(self::BASE) && !$usuario->hasDirectPermission($permiso)) {
                $usuario->givePermissionTo($permiso);
            }
        });

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();

        app()['cache']->forget('spatie.permission.cache');
    }
}
