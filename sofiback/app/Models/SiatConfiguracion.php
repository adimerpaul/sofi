<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Datos del emisor ante Impuestos, guardados en base para poder cambiarlos
 * desde la pantalla /impuestos (el token delegado caduca y hay que renovarlo).
 *
 * Es una fila unica y siempre de PRODUCCION: aca no se factura contra el
 * ambiente piloto, asi que codigo_ambiente queda fijo en 1 y la unica URL que
 * se usa es url_siat. La columna codigo_ambiente se conserva porque siat_cuis y
 * siat_cufds guardan con que ambiente se pidio cada codigo.
 */
class SiatConfiguracion extends Model
{
    use SoftDeletes;

    /** Ambiente del SIAT: 1 = PRODUCCION. */
    const AMBIENTE_PRODUCCION = 1;

    protected $table = 'siat_configuraciones';

    protected $fillable = [
        'razon_social',
        'nit',
        'token',
        'token_expira',
        'codigo_modalidad',
        'codigo_sistema',
        'codigo_sucursal',
        'codigo_punto_venta',
        'url_siat',
        'url_siat2',
    ];

    protected $casts = [
        'token_expira'       => 'datetime',
        'codigo_ambiente'    => 'integer',
        'codigo_modalidad'   => 'integer',
        'codigo_sucursal'    => 'integer',
        'codigo_punto_venta' => 'integer',
    ];

    public static function activa()
    {
        $config = static::query()->orderBy('id')->first();

        if (!$config) {
            $config = new static();
            $config->codigo_ambiente = self::AMBIENTE_PRODUCCION;
            $config->fill([
                'razon_social'       => config('siat.razon_social'),
                'nit'                => config('siat.nit'),
                'token'              => config('siat.token') ?: null,
                'codigo_modalidad'   => config('siat.modalidad'),
                'codigo_sistema'     => config('siat.codigo_sistema'),
                'codigo_sucursal'    => config('siat.codigo_sucursal'),
                'codigo_punto_venta' => config('siat.codigo_punto_venta'),
                'url_siat'           => config('siat.url_siat'),
                'url_siat2'          => config('siat.url_siat2'),
            ]);
            $config->token_expira = static::expiracionDelToken($config->token);
            $config->save();
        }

        return $config;
    }

    /** Base de los servicios SOAP. Siempre produccion. */
    public function urlBase()
    {
        return rtrim((string) $this->url_siat, '/') . '/';
    }

    /** WSDL de un servicio del SIAT, p.ej. 'FacturacionCodigos'. */
    public function wsdl($servicio)
    {
        return $this->urlBase() . $servicio . '?WSDL';
    }

    public function modalidadNombre()
    {
        return $this->codigo_modalidad === 1 ? 'ELECTRONICA EN LINEA' : 'COMPUTARIZADA EN LINEA';
    }

    /** true cuando el token ya vencio; la pantalla lo pinta en rojo. */
    public function tokenVencido()
    {
        return $this->token_expira && $this->token_expira->lessThan(now());
    }

    /** Lo que falta para poder hablar con el SIAT; vacio significa que esta listo. */
    public function faltantes()
    {
        $faltan = [];

        if (!trim((string) $this->token)) {
            $faltan[] = 'el token delegado';
        }
        if (!trim((string) $this->nit)) {
            $faltan[] = 'el NIT';
        }
        if (!trim((string) $this->codigo_sistema)) {
            $faltan[] = 'el código de sistema';
        }

        return $faltan;
    }

    /**
     * Vencimiento que trae el propio JWT en el claim `exp`. Se usa para
     * rellenar token_expira sin que nadie tenga que copiarlo a mano.
     */
    public static function expiracionDelToken($token)
    {
        $partes = explode('.', trim((string) $token));

        if (count($partes) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);

        return isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null;
    }
}
