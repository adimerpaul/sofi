<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permisos del modulo de Impuestos.
 *
 * Se separan ver / configurar / generar porque el token delegado y el codigo
 * de sistema son datos sensibles: quien opera caja puede necesitar ver si el
 * CUFD del dia esta vigente sin poder tocar la configuracion.
 */
class AddPermissionImpuestos extends Migration
{
    private const ROL = 'encargado';

    private const PERMISOS = [
        // Entrar a la pantalla y ver el estado de CUIS/CUFD.
        'impuestos',
        // Editar los datos del emisor y el token delegado.
        'impuestosConfigurar',
        // Pedir CUIS y CUFD al SIAT.
        'impuestosGenerar',
    ];

    public function up()
    {
        foreach (self::PERMISOS as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        $rol = Role::where('name', self::ROL)->where('guard_name', 'web')->first();
        if ($rol) {
            foreach (self::PERMISOS as $nombre) {
                $permiso = Permission::where('name', $nombre)->where('guard_name', 'web')->first();
                if ($permiso && !$rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
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
