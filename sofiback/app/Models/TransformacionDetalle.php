<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un producto de la transformacion: ORIGEN (sale) o DESTINO (entra). */
class TransformacionDetalle extends Model
{
    protected $table = 'transformacion_detalles';

    protected $fillable = ['transformacion_id', 'tipo', 'cod_prod', 'producto', 'unidad', 'kg', 'cajas'];

    protected $casts = [
        'kg'    => 'decimal:3',
        'cajas' => 'decimal:2',
    ];

    public function transformacion()
    {
        return $this->belongsTo(Transformacion::class);
    }
}
