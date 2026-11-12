<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso "Avance de ventas" (AvanceVentasController): objetivos del mes por
 * linea y avance de cada vendedor segun lo facturado.
 *
 * Lo reciben los administradores (quienes tienen el permiso usuario, el de
 * Usuarios: roles y permisos) y Tatiana Hurtado.
 */
class AddPermissionAvanceVentas extends Migration
{
    private const PERMISO = 'avanceventas';

    public function up()
    {
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        app()['cache']->forget('spatie.permission.cache');

        $usuarios = Permission::where('name', 'usuario')->where('guard_name', 'web')->exists()
            ? User::permission('usuario')->get()
            : collect();

        $tatiana = User::whereRaw("TRIM(Nombre1) LIKE 'TATIANA%'")
            ->whereRaw("TRIM(App1) LIKE 'HURTADO%'")
            ->get();
        if ($tatiana->isEmpty()) {
            echo "  Aviso: no se encontro el usuario Tatiana Hurtado; dar el permiso desde Usuarios.\n";
        }

        foreach ($usuarios->merge($tatiana)->unique('CodAut') as $usuario) {
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
