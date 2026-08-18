<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de vencimiento del token delegado.
 *
 * El JWT ya la trae en el claim `exp`, pero leerla implica decodificarlo cada
 * vez y no se puede consultar por SQL. Guardarla aparte permite avisar en
 * pantalla antes de que se caiga la facturacion por un token vencido.
 */
class AddTokenExpiraToSiatConfiguraciones extends Migration
{
    public function up()
    {
        Schema::table('siat_configuraciones', function (Blueprint $table) {
            $table->dateTime('token_expira')->nullable()->after('token');
        });

        // Se rellena con lo que diga el token que ya estaba cargado.
        foreach (DB::table('siat_configuraciones')->get() as $fila) {
            $expira = $this->expiracion($fila->token);

            if ($expira) {
                DB::table('siat_configuraciones')->where('id', $fila->id)->update(['token_expira' => $expira]);
            }
        }
    }

    public function down()
    {
        Schema::table('siat_configuraciones', function (Blueprint $table) {
            $table->dropColumn('token_expira');
        });
    }

    private function expiracion($token)
    {
        $partes = explode('.', trim((string) $token));

        if (count($partes) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);

        return isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null;
    }
}
