<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Cambiar el precio al cobrar un pedido deja de ser libre: sin este permiso el
 * precio queda con lo que trae el pedido o el catalogo.
 *
 * Arranca dado a todos los usuarios, directo y no por rol, para que no cambie
 * nada hoy y se le quite a quien corresponda desde la pantalla de Usuarios.
 */
class AddPermissionFacturacionPrecio extends Migration
{
    private const PERMISO = 'facturacionPrecio';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');

        User::query()->each(function ($usuario) use ($permiso) {
            if (!$usuario->hasDirectPermission($permiso)) {
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
