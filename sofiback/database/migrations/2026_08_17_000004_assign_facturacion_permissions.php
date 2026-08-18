<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Da los permisos de facturacion al rol que ya administra ventas.
 *
 * En Sofia los permisos se asignan por rol, no usuario por usuario, y hoy
 * "encargado" es el que tiene ventas y usuario. Los demas roles se dejan como
 * estan: quien quiera dar acceso a otro lo hace desde la pantalla de Usuarios.
 */
class AssignFacturacionPermissions extends Migration
{
    private const ROL = 'encargado';

    private const PERMISOS = ['facturacion', 'facturacionNueva', 'facturacionAnular'];

    public function up()
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if (!$rol) {
            return;
        }

        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($permiso && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if (!$rol) {
            return;
        }

        foreach (self::PERMISOS as $nombre) {
            $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($permiso) {
                $rol->revokePermissionTo($permiso);
            }
        }

        app()['cache']->forget('spatie.permission.cache');
    }
}
