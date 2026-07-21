<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas')) {
            try {
                DB::statement('ALTER TABLE ventas MODIFY total DECIMAL(14,3) NULL');
            } catch (\Throwable $e) {
                // Keep migration non-blocking when column already matches.
            }

            Schema::table('ventas', function (Blueprint $table) {
                if (!Schema::hasColumn('ventas', 'comanda_legacy')) {
                    $table->unsignedBigInteger('comanda_legacy')->nullable()->after('id');
                    $table->index('comanda_legacy');
                }
                if (!Schema::hasColumn('ventas', 'nrofac_legacy')) {
                    $table->unsignedBigInteger('nrofac_legacy')->nullable()->after('comanda_legacy');
                    $table->index('nrofac_legacy');
                }
                if (!Schema::hasColumn('ventas', 'codaut_factura_legacy')) {
                    $table->unsignedBigInteger('codaut_factura_legacy')->nullable()->after('nrofac_legacy');
                    $table->unique('codaut_factura_legacy');
                }
            });
        }

        if (Schema::hasTable('venta_detalles')) {
            try {
                DB::statement('ALTER TABLE venta_detalles MODIFY cantidad DECIMAL(12,3) NULL');
                DB::statement('ALTER TABLE venta_detalles MODIFY precio DECIMAL(12,3) NULL');
            } catch (\Throwable $e) {
                // Keep migration non-blocking when columns already match.
            }

            Schema::table('venta_detalles', function (Blueprint $table) {
                if (!Schema::hasColumn('venta_detalles', 'codaut_legacy')) {
                    $table->unsignedBigInteger('codaut_legacy')->nullable()->after('id');
                    $table->unique('codaut_legacy');
                }
            });
        }

        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                if (Schema::hasColumn('clientes', 'cod_aut')) {
                    $table->index('cod_aut');
                }
            });
        }

        if (Schema::hasTable('productos')) {
            Schema::table('productos', function (Blueprint $table) {
                if (!Schema::hasColumn('productos', 'codaut_legacy')) {
                    $table->unsignedBigInteger('codaut_legacy')->nullable()->after('id');
                    $table->index('codaut_legacy');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventas')) {
            Schema::table('ventas', function (Blueprint $table) {
                if (Schema::hasColumn('ventas', 'codaut_factura_legacy')) {
                    $table->dropUnique(['codaut_factura_legacy']);
                    $table->dropColumn('codaut_factura_legacy');
                }
                if (Schema::hasColumn('ventas', 'nrofac_legacy')) {
                    $table->dropIndex(['nrofac_legacy']);
                    $table->dropColumn('nrofac_legacy');
                }
                if (Schema::hasColumn('ventas', 'comanda_legacy')) {
                    $table->dropIndex(['comanda_legacy']);
                    $table->dropColumn('comanda_legacy');
                }
            });
        }

        if (Schema::hasTable('venta_detalles')) {
            Schema::table('venta_detalles', function (Blueprint $table) {
                if (Schema::hasColumn('venta_detalles', 'codaut_legacy')) {
                    $table->dropUnique(['codaut_legacy']);
                    $table->dropColumn('codaut_legacy');
                }
            });
        }

        if (Schema::hasTable('productos')) {
            Schema::table('productos', function (Blueprint $table) {
                if (Schema::hasColumn('productos', 'codaut_legacy')) {
                    $table->dropIndex(['codaut_legacy']);
                    $table->dropColumn('codaut_legacy');
                }
            });
        }
    }
};

