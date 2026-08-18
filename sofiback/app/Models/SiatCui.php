<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CUIS vigente por sucursal y punto de venta. Sin uno no se puede pedir CUFD.
 */
class SiatCui extends Model
{
    use SoftDeletes;

    protected $table = 'siat_cuis';

    protected $fillable = [
        'codigo',
        'fecha_vigencia',
        'codigo_sucursal',
        'codigo_punto_venta',
        'codigo_ambiente',
        'user_id',
        'respuesta',
    ];

    protected $casts = [
        'fecha_vigencia'     => 'datetime',
        'codigo_sucursal'    => 'integer',
        'codigo_punto_venta' => 'integer',
        'codigo_ambiente'    => 'integer',
    ];

    /**
     * El ultimo que todavia no caduco para ese punto de venta y ambiente.
     *
     * Se filtra por ambiente a proposito: un CUIS de piloto no sirve para
     * emitir en produccion y viceversa.
     */
    public static function vigente($sucursal, $puntoVenta, $ambiente)
    {
        return static::where('codigo_sucursal', $sucursal)
            ->where('codigo_punto_venta', $puntoVenta)
            ->where('codigo_ambiente', $ambiente)
            ->where('fecha_vigencia', '>=', now())
            ->orderByDesc('id')
            ->first();
    }

    public function getVigenteAttribute()
    {
        return $this->fecha_vigencia && $this->fecha_vigencia->greaterThanOrEqualTo(now());
    }
}
