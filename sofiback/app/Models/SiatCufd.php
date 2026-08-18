<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CUFD del dia. Caduca cada 24 horas; el codigo de control es lo que despues
 * entra en el calculo del CUF de cada factura.
 */
class SiatCufd extends Model
{
    use SoftDeletes;

    protected $table = 'siat_cufds';

    protected $fillable = [
        'codigo',
        'codigo_control',
        'direccion',
        'fecha_vigencia',
        'codigo_sucursal',
        'codigo_punto_venta',
        'codigo_ambiente',
        'siat_cui_id',
        'user_id',
        'respuesta',
    ];

    protected $casts = [
        'fecha_vigencia'     => 'datetime',
        'codigo_sucursal'    => 'integer',
        'codigo_punto_venta' => 'integer',
        'codigo_ambiente'    => 'integer',
    ];

    public static function vigente($sucursal, $puntoVenta, $ambiente)
    {
        return static::where('codigo_sucursal', $sucursal)
            ->where('codigo_punto_venta', $puntoVenta)
            ->where('codigo_ambiente', $ambiente)
            ->where('fecha_vigencia', '>=', now())
            ->orderByDesc('id')
            ->first();
    }

    public function cui()
    {
        return $this->belongsTo(SiatCui::class, 'siat_cui_id');
    }

    public function getVigenteAttribute()
    {
        return $this->fecha_vigencia && $this->fecha_vigencia->greaterThanOrEqualTo(now());
    }
}
