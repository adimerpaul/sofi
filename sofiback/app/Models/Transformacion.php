<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transformacion de productos: kilos que salen de unos productos (ORIGEN) y
 * entran a otros (DESTINO). El efecto en inventario va a tbstock y
 * tbproductos.stock_actual (ver TransformacionController).
 */
class Transformacion extends Model
{
    use SoftDeletes;

    protected $table = 'transformaciones';

    protected $fillable = [
        'user_id', 'fecha', 'hora', 'estado', 'observacion',
        'total_origen_kg', 'total_destino_kg',
        'motivo_anulacion', 'anulado_por', 'anulado_at',
    ];

    protected $casts = [
        'fecha'            => 'date:Y-m-d',
        'total_origen_kg'  => 'decimal:3',
        'total_destino_kg' => 'decimal:3',
        'anulado_at'       => 'datetime:Y-m-d H:i:s',
    ];

    public function detalles()
    {
        return $this->hasMany(TransformacionDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'CodAut');
    }

    public function anulador()
    {
        return $this->belongsTo(User::class, 'anulado_por', 'CodAut');
    }
}
