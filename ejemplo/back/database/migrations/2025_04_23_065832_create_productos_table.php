<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 25);
            $table->string('imagen', 255)->default('uploads/default.png')->nullable();
            $table->unsignedBigInteger('producto_grupo_id')->nullable();
            $table->foreign('producto_grupo_id')->references('id')->on('producto_grupos');
            $table->unsignedBigInteger('producto_grupo_padre_id')->nullable();
            $table->foreign('producto_grupo_padre_id')->references('id')->on('producto_grupo_padres');
            $table->string('nombre', 105);
            $table->string('tipo_producto', 3);
            for($i=1; $i<=13; $i++){
                $table->decimal('precio'.$i, 10, 3);
            }
            $table->string('codigo_unidad', 15)->nullable();
            $table->decimal('unidades_caja', 10, 2)->nullable();
            $table->decimal('cantidad_presentacion', 10, 3)->nullable();
            $table->string('tipo', 255)->default('NORMAL')->nullable();
            $table->string('oferta', 255)->default(' ')->nullable();
            $table->string('codigo_producto_sin', 100)->nullable();
            $table->string('presentacion', 300)->nullable();
            $table->string('codigo_grupo_sin', 100)->nullable();
            $table->decimal('credito', 10, 3)->nullable();
//            $table->string('imagen',100)->nullable();
            $table->boolean('active')->default(true)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
