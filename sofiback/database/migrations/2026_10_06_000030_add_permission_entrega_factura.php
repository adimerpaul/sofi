<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Menu "Entregas facturación": el reporte de entregas armado sobre
 * facturacion, que reemplaza al de /entrega (sistema anterior).
 *
 * Arranca dado a quienes ya ven el reporte de entregas antiguo, directo y no
 * por rol, para que lo tengan sin pedirlo; despues se ajusta desde Usuarios.
 */
class AddPermissionEntregaFactura extends Migration
{
    private const PERMISO = 'entregaFactura';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');

        User::query()->each(function ($usuario) use ($permiso) {
            if ($usuario->hasPermissionTo('entrega') && !$usuario->hasDirectPermission($permiso)) {
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
