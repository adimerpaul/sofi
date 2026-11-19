<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "horariosPedido": cambiar la hora limite de cada tipo de pedido y
 * habilitar que se siga mandando despues de la hora.
 *
 * Lo reciben VENTAS GENERALES y Tania Mamani.
 */
class AddPermissionHorariosPedido extends Migration
{
    private const PERMISO = 'horariosPedido';

    private const PERSONAS = [['VENTAS', 'GENERALES'], ['TANIA', 'MAMANI']];

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        foreach (self::PERSONAS as [$nombre, $apellido]) {
            $usuarios = User::whereRaw('TRIM(Nombre1) LIKE ?', [$nombre . '%'])
                ->whereRaw('TRIM(App1) LIKE ?', [$apellido . '%'])
                ->get();
            if ($usuarios->isEmpty()) {
                echo "  Aviso: no se encontro a $nombre $apellido; dar el permiso desde Usuarios.\n";
            }
            foreach ($usuarios as $usuario) {
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
