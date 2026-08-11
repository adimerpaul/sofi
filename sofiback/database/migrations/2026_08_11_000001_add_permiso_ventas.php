<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AddPermisoVentas extends Migration
{
    /** Roles que reciben la nueva opcion de menu "Ventas". */
    private $roles = ['encargado', 'supervisor', 'supervisor2'];

    /**
     * Crea el permiso 'ventas' de la pantalla de Ventas.
     *
     * Se usa Query Builder en vez de los modelos de spatie a proposito:
     * Eloquent escribe los timestamps con Carbon, y la version instalada
     * (2.60) revienta en PHP 8.2+, que es lo que tumba `php artisan` en
     * algunos entornos. Con SQL plano la migracion corre en cualquier PHP.
     *
     * Tampoco se reejecuta PermissionSeeder: ese hace syncPermissions y
     * borraria los permisos que el administrador haya ajustado desde la app.
     */
    public function up()
    {
        $ahora = date('Y-m-d H:i:s');

        $permisoId = DB::table('permissions')
            ->where('name', 'ventas')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$permisoId) {
            $permisoId = DB::table('permissions')->insertGetId([
                'name'       => 'ventas',
                'guard_name' => 'web',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $rolesIds = DB::table('roles')
            ->whereIn('name', $this->roles)
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($rolesIds as $rolId) {
            $yaAsignado = DB::table('role_has_permissions')
                ->where('permission_id', $permisoId)
                ->where('role_id', $rolId)
                ->exists();

            if (!$yaAsignado) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permisoId,
                    'role_id'       => $rolId,
                ]);
            }
        }

        $this->olvidarCache();
    }

    public function down()
    {
        $permisoId = DB::table('permissions')
            ->where('name', 'ventas')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permisoId) {
            DB::table('role_has_permissions')->where('permission_id', $permisoId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permisoId)->delete();
            DB::table('permissions')->where('id', $permisoId)->delete();
        }

        $this->olvidarCache();
    }

    /**
     * spatie cachea permisos 24 horas; sin esto el permiso nuevo no surte
     * efecto hasta que expire. Va en try/catch para que un problema de cache
     * nunca deje la migracion a medias.
     */
    private function olvidarCache()
    {
        try {
            Cache::forget(config('permission.cache.key', 'spatie.permission.cache'));
        } catch (\Throwable $e) {
            // Si el store de cache no esta disponible basta con
            // `php artisan permission:cache-reset` despues de migrar.
        }
    }
}
