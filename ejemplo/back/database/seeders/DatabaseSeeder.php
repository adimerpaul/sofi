<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void{
//        User::insert([
//            [
//                'name' => 'Administrador',
//                'username' => 'admin',
//                'agencia' => 'Sofia',
//                'role' => 'Admin',
//                'email' => 'admin@example.com',
//                'password' => Hash::make('admin123Admin'),
//                'active' => '1',
////                'avatar' => 'default.png',
//                'created_at' => now(),
//                'updated_at' => now(),
//            ],
//        ]);
//        users_202510241958.sql
        $sqlFile = base_path('database/seeders/users_202602220930.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        $permisos = [
            'Usuarios',
            'Impuestos',
            'Productos',
            'Ventas',
            'Nueva Venta',
            'Proveedores',
            'Compras',
            'Nueva Compra',
            'Clientes',
            'Productos por vencer',
            'Productos vencidos',
            'Pedidos',
            'Nuevo Pedido',
            'Mis pedidos totales',
            'Mapa cliente',
            'Mapa cliente zonas',
            'Mapa zona',
            'Auxiliar de camara',
            'Digitador factura',
            'Rutas',
            'Despacho',
            'Cobranzas',
        ];
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }
        $permisosAll = Permission::all();

        $admin = User::where('id', 1)->first();
        $admin->syncPermissions($permisosAll);

        $gruposPadres = [
            ['nombre' => 'BANDEJAS', 'codigo' => '1'],
            ['nombre' => 'SISTEMA', 'codigo' => 'F'],
            ['nombre' => 'CARNE DE CERDO', 'codigo' => '2'],
            ['nombre' => 'CARNE DE POLLO', 'codigo' => '3'],
            ['nombre' => 'CONSERVAS', 'codigo' => '4'],
            ['nombre' => 'EMBUTIDOS', 'codigo' => '5'],
            ['nombre' => 'OTROS', 'codigo' => '6'],
            ['nombre' => 'CONGELADOS', 'codigo' => '7'],
            ['nombre' => 'FIAMBRES', 'codigo' => '8'],
            ['nombre' => 'PODIUM', 'codigo' => '9'],
            ['nombre' => 'CARNE DE RES GANCHO', 'codigo' => '10'],
            ['nombre' => 'ENLATADOS', 'codigo' => '011'],
            ['nombre' => 'PAPAS FRITAS', 'codigo' => '012'],
            ['nombre' => 'APIS', 'codigo' => '13'],
            ['nombre' => 'ACEITE', 'codigo' => '90'],
            ['nombre' => 'FRUTAS Y VERDURAS CONGELADAS', 'codigo' => '80'],
            ['nombre' => 'PESCADO', 'codigo' => '014'],
        ];
        foreach($gruposPadres as $grupoPadre){
            DB::table('producto_grupo_padres')->insert([
                'nombre' => $grupoPadre['nombre'],
                'codigo' => $grupoPadre['codigo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $gruposHijos = [
            ['codigo' => '11', 'producto_grupo_padre_id' => 1, 'nombre' => 'BANDEJA DE CERDO'],
            ['codigo' => '12', 'producto_grupo_padre_id' => 1, 'nombre' => 'BANDEJA DE COSTILLA ON'],
            ['codigo' => '13', 'producto_grupo_padre_id' => 1, 'nombre' => 'BANDEJA DE PAVO'],
            ['codigo' => '14', 'producto_grupo_padre_id' => 1, 'nombre' => 'BANDEJA DE POLLO'],
            ['codigo' => '21', 'producto_grupo_padre_id' => 2, 'nombre' => 'CORTES'],
            ['codigo' => '22', 'producto_grupo_padre_id' => 2, 'nombre' => 'ENTERO'],
            ['codigo' => '31', 'producto_grupo_padre_id' => 3, 'nombre' => 'FRIAL'],
            ['codigo' => '32', 'producto_grupo_padre_id' => 3, 'nombre' => 'TROZADOS'],
            ['codigo' => '41', 'producto_grupo_padre_id' => 4, 'nombre' => 'ATUN'],
            ['codigo' => '51', 'producto_grupo_padre_id' => 5, 'nombre' => 'CHORIZO'],
            ['codigo' => '52', 'producto_grupo_padre_id' => 5, 'nombre' => 'JAMON'],
            ['codigo' => '53', 'producto_grupo_padre_id' => 5, 'nombre' => 'MORTADELA'],
            ['codigo' => '61', 'producto_grupo_padre_id' => 6, 'nombre' => 'VARIOS'],
            ['codigo' => '71', 'producto_grupo_padre_id' => 7, 'nombre' => 'PROCESADOS'],
            ['codigo' => '54', 'producto_grupo_padre_id' => 5, 'nombre' => 'PATE'],
            ['codigo' => '55', 'producto_grupo_padre_id' => 5, 'nombre' => 'SALCHICHA'],
            ['codigo' => '81', 'producto_grupo_padre_id' => 8, 'nombre' => 'AHUMADO'],
            ['codigo' => '82', 'producto_grupo_padre_id' => 8, 'nombre' => 'QUESO ENRROLLADO'],
            ['codigo' => '83', 'producto_grupo_padre_id' => 8, 'nombre' => 'SECOS'],
            ['codigo' => '72', 'producto_grupo_padre_id' => 7, 'nombre' => 'HAMBURGUESA'],
            ['codigo' => '73', 'producto_grupo_padre_id' => 7, 'nombre' => 'NUGGETS'],
            ['codigo' => '91', 'producto_grupo_padre_id' => 9, 'nombre' => 'PODIUM'],
            ['codigo' => '101', 'producto_grupo_padre_id' => 10, 'nombre' => 'CARNE DE RES'],
            ['codigo' => '0111', 'producto_grupo_padre_id' => 11, 'nombre' => 'CONSERVAS'],
            ['codigo' => '0121', 'producto_grupo_padre_id' => 12, 'nombre' => 'PAPAS FRITAS'],
            ['codigo' => '7077', 'producto_grupo_padre_id' => 13, 'nombre' => 'API'],
            ['codigo' => '8010', 'producto_grupo_padre_id' => 14, 'nombre' => 'FRUTAS Y VERDURAS CONGELADAS'],
            ['codigo' => '901', 'producto_grupo_padre_id' => 15, 'nombre' => 'ACEITE'],
            ['codigo' => '0141', 'producto_grupo_padre_id' => 16, 'nombre' => 'PESCADO'],
        ];
        foreach($gruposHijos as $grupoHijo) {
            DB::table('producto_grupos')->insert([
                'codigo' => $grupoHijo['codigo'],
                'producto_grupo_padre_id' => $grupoHijo['producto_grupo_padre_id'],
                'nombre' => $grupoHijo['nombre'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sqlFile = base_path('database/seeders/productos_202602220930.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        error_log("Productos seed 202509302046 executed");

//        clientes_202602190422.sql
        $sqlFile = base_path('database/seeders/clientes_202602190422.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        error_log("Clientes seed 202602190422 executed");
    }
}
