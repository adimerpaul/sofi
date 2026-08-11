<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AddPermisoVentas extends Migration
{
    /** Roles que reciben la nueva opcion de menu "Ventas". */
    private $roles = ['encargado', 'supervisor', 'supervisor2'];

    /**
     * Agrega el permiso de la pantalla de Ventas.
     *
     * No se reejecuta PermissionSeeder a proposito: ese hace syncPermissions
     * y borraria los permisos que el administrador haya ajustado desde la app.
     * Aca solo se suma el permiso nuevo.
     */
    public function up()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::firstOrCreate(['name' => 'ventas', 'guard_name' => 'web']);

        foreach ($this->roles as $nombre) {
            $rol = Role::where('name', $nombre)->where('guard_name', 'web')->first();
            if ($rol && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permiso = Permission::where('name', 'ventas')->where('guard_name', 'web')->first();
        if ($permiso) {
            $permiso->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
