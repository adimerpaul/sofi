<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "motivosAnulacion": crear, editar, activar/inactivar y borrar los
 * motivos de anulacion de facturas.
 *
 * Lo reciben los administradores (quienes tienen el permiso usuario, el de
 * Usuarios: roles y permisos), Tatiana Hurtado y Tania Mamani.
 */
class AddPermissionMotivosAnulacion extends Migration
{
    private const PERMISO = 'motivosAnulacion';

    private const PERSONAS = [['TATIANA', 'HURTADO'], ['TANIA', 'MAMANI']];

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        $usuarios = Permission::where('name', 'usuario')->where('guard_name', 'web')->exists()
            ? User::permission('usuario')->get()
            : collect();

        foreach (self::PERSONAS as [$nombre, $apellido]) {
            $persona = User::whereRaw('TRIM(Nombre1) LIKE ?', [$nombre . '%'])
                ->whereRaw('TRIM(App1) LIKE ?', [$apellido . '%'])
                ->get();
            if ($persona->isEmpty()) {
                echo "  Aviso: no se encontro a $nombre $apellido; dar el permiso desde Usuarios.\n";
            }
            $usuarios = $usuarios->merge($persona);
        }

        foreach ($usuarios->unique('CodAut') as $usuario) {
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
