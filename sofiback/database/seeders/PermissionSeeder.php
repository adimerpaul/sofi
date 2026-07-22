<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Crea los permisos del menú (uno por opción de MainLayout.vue),
     * los roles equivalentes a las listas de CI que estaban hardcodeadas
     * en el frontend, y los asigna a los usuarios actuales.
     */
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = [
            'visita',
            'clientevisita',
            'mispedidos',
            'mispedidostotales',
            'clientes',
            'pendientes',
            'clientepedido',
            'listpedido',
            'cobrosrealizados',
            'cobranza',
            'miscobranzas',
            'productos',
            'nopedido',
            'horariosenvio',
            'generar',
            'genreporte',
            'ruta',
            'despacho',
            'avance',
            'entrega',
            'reporte',
            'almacen',
            'almacenVerificar',
            'almacenVerificado',
            'modifica',
            'monitoreo',
            'mapavendedor',
            'mapavendedorvisita',
            'mapacliente',
            'altacliente',
            'bonificaciones',
            'clientefotografias',
            'pedidos',
            'encuestasIndex',
            'cambioscalidad',
            'usuario',
        ];
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $roles = [
            'vendedor' => [
                'visita', 'clientevisita', 'mispedidos', 'cobrosrealizados', 'cobranza',
                'miscobranzas', 'productos', 'nopedido', 'avance', 'altacliente', 'cambioscalidad',
            ],
            'cobrador' => [
                'cobrosrealizados', 'cobranza', 'miscobranzas', 'altacliente', 'cambioscalidad',
                'pedidos', 'encuestasIndex',
            ],
            'encargado' => [
                'horariosenvio', 'generar', 'genreporte', 'entrega', 'reporte',
                'pedidos', 'encuestasIndex', 'usuario',
            ],
            'digitador' => [
                'mispedidostotales', 'listpedido',
            ],
            'despachador' => [
                'ruta', 'despacho',
            ],
            'supervisor' => [
                'genreporte', 'entrega', 'modifica', 'monitoreo', 'mapavendedor',
                'mapavendedorvisita', 'bonificaciones', 'clientefotografias',
            ],
            'supervisor2' => [
                'genreporte', 'despacho', 'mapavendedor', 'mapavendedorvisita',
            ],
            'almacen' => [
                'almacen', 'almacenVerificar', 'almacenVerificado',
            ],
            'asignar' => [
                'mapacliente',
            ],
        ];
        foreach ($roles as $rol => $permisosRol) {
            $role = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
            $role->syncPermissions($permisosRol);
        }

        // Listas de CI que estaban hardcodeadas en sofifront/src/layouts/MainLayout.vue
        $usuariosPorRol = [
            'vendedor' => [
                '4035534', '5726715', '5572091', '10060810', '3779602', '12612870', '1593578',
                '33555433', '3520335', '5676554', '7422201', '9876785', '7360035', '5067737',
                '7331330', '7308976', '7377278', '5938578', '7351953', '7329688', '7288817',
                '7306963', '5773491', '3544875019', '7312297', '7326952',
            ],
            'encargado' => ['123321', '7205489', '7277481'],
            'asignar' => ['7205489', '7308976', '123321', '7277481'],
            'almacen' => ['7308976', '7377278', '7205489', '7277481'],
            'cobrador' => ['4035534'],
            'despachador' => [
                '7205489', 'A1SUD', 'A2NORTE', 'A3CENTRO', 'A4BOLIVAR', 'A5APOYO', 'A6APOYO2',
                'C1RECOGE', 'B3LLALLAGUA', 'B4CARACOLLO', '7277481', 'B1HUANUNI', 'MOTO1',
                'MOTO2', 'B2CHALLAPATA',
            ],
            'supervisor' => ['7308976', '7329688', '7288817', '7312297', '7205489', '7277481'],
            'supervisor2' => ['5726715'],
            'digitador' => ['1223334444', '7308976', '7329688', '7277481', '123321', '7205489', '7312297'],
        ];
        foreach ($usuariosPorRol as $rol => $cis) {
            $users = User::whereIn(DB::raw('TRIM(ci)'), $cis)->get();
            foreach ($users as $user) {
                $user->assignRole($rol);
            }
            $encontrados = $users->map(function ($u) {
                return trim($u->ci);
            })->unique();
            foreach (array_diff($cis, $encontrados->all()) as $ciFaltante) {
                if (isset($this->command)) {
                    $this->command->warn("Rol {$rol}: no existe usuario con ci {$ciFaltante}");
                }
            }
        }

        // Permisos directos de CIs con accesos puntuales en MainLayout.vue
        $permisosDirectos = [
            '7329536' => ['clientes', 'pendientes', 'clientepedido', 'cobrosrealizados'],
            '123321' => ['mapavendedor', 'mapavendedorvisita', 'bonificaciones', 'clientefotografias'],
        ];
        foreach ($permisosDirectos as $ci => $permisosUser) {
            foreach (User::whereRaw('TRIM(ci) = ?', [$ci])->get() as $user) {
                $user->givePermissionTo($permisosUser);
            }
        }
    }
}
