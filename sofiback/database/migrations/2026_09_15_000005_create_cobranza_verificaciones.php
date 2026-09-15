<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verificacion de la facturacion del dia por cobranzas.
 *
 * Es la "Cancela" de la pantalla de cuentas por cobrar del sistema de caja:
 * el cobrador mira cada comprobante contra lo que trajo el camion, pone el
 * monto que de verdad recibio y lo tilda. Una fila por comprobante.
 */
class CreateCobranzaVerificaciones extends Migration
{
    private const PERMISO = 'cobranzasverificar';

    public function up()
    {
        if (!Schema::hasTable('cobranza_verificaciones')) {
            Schema::create('cobranza_verificaciones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('factura_id')->unique();
                $table->date('fecha')->index();
                $table->string('placa', 100)->nullable();
                // Lo que decia el comprobante y lo que traia el camion al
                // momento de verificar, para ver despues si algo cambio.
                $table->decimal('monto_facturado', 12, 2)->default(0);
                $table->decimal('monto_recogido', 12, 2)->default(0);
                $table->decimal('monto_verificado', 12, 2)->default(0);
                $table->boolean('verificado')->default(false);
                $table->string('observacion', 190)->nullable();
                $table->integer('user_id')->nullable();
                $table->string('verificado_por', 150)->nullable();
                $table->dateTime('verificado_en')->nullable();
                $table->timestamps();
            });
        }

        // Va a los mismos que ya ven el recojo: cobrador y encargado, por rol o
        // por permiso directo.
        $permiso = Permission::findOrCreate(self::PERMISO, 'web');
        Role::where('guard_name', 'web')->get()->each(function ($rol) use ($permiso) {
            if ($rol->hasPermissionTo('cobranzasrecojo') && !$rol->hasPermissionTo($permiso)) {
                $rol->givePermissionTo($permiso);
            }
        });
        User::permission('cobranzasrecojo')->get()->each(function ($usuario) use ($permiso) {
            if ($usuario->hasDirectPermission('cobranzasrecojo') && !$usuario->hasDirectPermission($permiso)) {
                $usuario->givePermissionTo($permiso);
            }
        });

        app()['cache']->forget('spatie.permission.cache');
    }

    public function down()
    {
        Schema::dropIfExists('cobranza_verificaciones');
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
}
