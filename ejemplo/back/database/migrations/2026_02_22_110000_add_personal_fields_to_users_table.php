<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cod_aut')) {
                $table->integer('cod_aut')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('cod_aut');
            }
            if (!Schema::hasColumn('users', 'ci')) {
                $table->string('ci', 15)->nullable()->after('fecha_nacimiento');
            }
            if (!Schema::hasColumn('users', 'cod_prof')) {
                $table->integer('cod_prof')->nullable()->after('ci');
            }
            if (!Schema::hasColumn('users', 'app1')) {
                $table->string('app1', 20)->nullable()->after('cod_prof');
            }
            if (!Schema::hasColumn('users', 'app2')) {
                $table->string('app2', 20)->nullable()->after('app1');
            }
            if (!Schema::hasColumn('users', 'nombre1')) {
                $table->string('nombre1', 15)->nullable()->after('app2');
            }
            if (!Schema::hasColumn('users', 'nombre2')) {
                $table->string('nombre2', 15)->nullable()->after('nombre1');
            }
            if (!Schema::hasColumn('users', 'salario')) {
                $table->decimal('salario', 12, 0)->nullable()->after('nombre2');
            }
            if (!Schema::hasColumn('users', 'direccion')) {
                $table->string('direccion', 250)->nullable()->after('salario');
            }
            if (!Schema::hasColumn('users', 'cod_car')) {
                $table->integer('cod_car')->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('users', 'nro')) {
                $table->integer('nro')->nullable()->after('cod_car');
            }
            if (!Schema::hasColumn('users', 'nro_alm')) {
                $table->integer('nro_alm')->nullable()->after('nro');
            }
            if (!Schema::hasColumn('users', 'acceso_emp')) {
                $table->boolean('acceso_emp')->nullable()->after('nro_alm');
            }
            if (!Schema::hasColumn('users', 'placa')) {
                $table->string('placa', 100)->nullable()->after('acceso_emp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'cod_aut',
                'fecha_nacimiento',
                'ci',
                'cod_prof',
                'app1',
                'app2',
                'nombre1',
                'nombre2',
                'salario',
                'direccion',
                'cod_car',
                'nro',
                'nro_alm',
                'acceso_emp',
                'placa',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
