<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'id_externo')) $table->string('id_externo')->nullable()->after('id');
            if (!Schema::hasColumn('clientes', 'nit')) $table->string('nit')->nullable()->after('id');
            if (!Schema::hasColumn('clientes', 'cod_aut')) $table->string('cod_aut')->nullable()->after('id');
            if (!Schema::hasColumn('clientes', 'cod_ciudad')) $table->string('cod_ciudad', 4)->nullable()->after('id_externo');
            if (!Schema::hasColumn('clientes', 'cod_nacio')) $table->string('cod_nacio', 4)->nullable()->after('cod_ciudad');
            if (!Schema::hasColumn('clientes', 'cod_car')) $table->integer('cod_car')->nullable()->after('cod_nacio');
            if (!Schema::hasColumn('clientes', 'est_civ')) $table->string('est_civ', 50)->nullable()->after('direccion');
            if (!Schema::hasColumn('clientes', 'edad')) $table->string('edad', 3)->nullable()->after('est_civ');
            if (!Schema::hasColumn('clientes', 'empresa')) $table->string('empresa', 150)->nullable()->after('edad');
            if (!Schema::hasColumn('clientes', 'categoria')) $table->integer('categoria')->nullable()->after('empresa');
            if (!Schema::hasColumn('clientes', 'imp_pieza')) $table->decimal('imp_pieza', 10, 2)->nullable()->after('categoria');
            if (!Schema::hasColumn('clientes', 'ci_vend')) $table->string('ci_vend', 15)->nullable()->after('imp_pieza');
            if (!Schema::hasColumn('clientes', 'list_blanck')) $table->boolean('list_blanck')->default(false)->after('ci_vend');
            if (!Schema::hasColumn('clientes', 'motivo_list_black')) $table->string('motivo_list_black', 90)->nullable()->after('list_blanck');
            if (!Schema::hasColumn('clientes', 'list_black')) $table->boolean('list_black')->default(false)->after('motivo_list_black');
            if (!Schema::hasColumn('clientes', 'tipo_paciente')) $table->string('tipo_paciente', 90)->nullable()->after('list_black');
            if (!Schema::hasColumn('clientes', 'supra_canal')) $table->string('supra_canal', 5)->nullable()->after('tipo_paciente');
            if (!Schema::hasColumn('clientes', 'canal')) $table->string('canal', 80)->nullable()->after('supra_canal');
            if (!Schema::hasColumn('clientes', 'subcanal')) $table->string('subcanal', 20)->nullable()->after('canal');
            if (!Schema::hasColumn('clientes', 'zona')) $table->string('zona', 20)->nullable()->after('subcanal');
            if (!Schema::hasColumn('clientes', 'latitud')) $table->decimal('latitud', 10, 7)->nullable()->after('zona');
            if (!Schema::hasColumn('clientes', 'longitud')) $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            if (!Schema::hasColumn('clientes', 'transporte')) $table->string('transporte')->nullable()->after('longitud');
            if (!Schema::hasColumn('clientes', 'territorio')) $table->string('territorio')->nullable()->after('transporte');
            if (!Schema::hasColumn('clientes', 'codcli')) $table->integer('codcli')->nullable()->after('territorio');
            if (!Schema::hasColumn('clientes', 'clinew')) $table->string('clinew', 3)->nullable()->after('codcli');
            if (!Schema::hasColumn('clientes', 'venta_estado')) $table->string('venta_estado', 100)->default('ACTIVO')->after('clinew');
            if (!Schema::hasColumn('clientes', 'complto')) $table->string('complto', 5)->nullable()->after('venta_estado');
            if (!Schema::hasColumn('clientes', 'tipodocu')) $table->integer('tipodocu')->nullable()->after('complto');
            if (!Schema::hasColumn('clientes', 'lu')) $table->boolean('lu')->default(false)->after('tipodocu');
            if (!Schema::hasColumn('clientes', 'ma')) $table->boolean('ma')->default(false)->after('lu');
            if (!Schema::hasColumn('clientes', 'mi')) $table->boolean('mi')->default(false)->after('ma');
            if (!Schema::hasColumn('clientes', 'ju')) $table->boolean('ju')->default(false)->after('mi');
            if (!Schema::hasColumn('clientes', 'vi')) $table->boolean('vi')->default(false)->after('ju');
            if (!Schema::hasColumn('clientes', 'sa')) $table->boolean('sa')->default(false)->after('vi');
            if (!Schema::hasColumn('clientes', 'do')) $table->boolean('do')->default(false)->after('sa');
            if (!Schema::hasColumn('clientes', 'correcli')) $table->string('correcli', 50)->nullable()->after('do');
            if (!Schema::hasColumn('clientes', 'canmayni')) $table->boolean('canmayni')->default(false)->after('correcli');
            if (!Schema::hasColumn('clientes', 'baja')) $table->boolean('baja')->default(false)->after('canmayni');
            if (!Schema::hasColumn('clientes', 'profecion')) $table->string('profecion', 60)->nullable()->after('baja');
            if (!Schema::hasColumn('clientes', 'waths')) $table->boolean('waths')->default(false)->after('profecion');
            if (!Schema::hasColumn('clientes', 'ctas_activo')) $table->boolean('ctas_activo')->default(false)->after('waths');
            if (!Schema::hasColumn('clientes', 'ctas_mont')) $table->decimal('ctas_mont', 10, 2)->nullable()->after('ctas_activo');
            if (!Schema::hasColumn('clientes', 'ctas_dias')) $table->integer('ctas_dias')->nullable()->after('ctas_mont');
            if (!Schema::hasColumn('clientes', 'sexo')) $table->string('sexo', 20)->nullable()->after('ctas_dias');
            if (!Schema::hasColumn('clientes', 'noesempre')) $table->boolean('noesempre')->default(false)->after('sexo');
            if (!Schema::hasColumn('clientes', 'tarjeta')) $table->string('tarjeta', 20)->nullable()->after('noesempre');
            if (!Schema::hasColumn('clientes', 'fotos')) $table->json('fotos')->nullable()->after('tarjeta');
        });
    }

    public function down(): void
    {
        // No destructive rollback for client extension migration.
    }
};
