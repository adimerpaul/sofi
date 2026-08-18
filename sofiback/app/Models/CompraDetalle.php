<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Linea de una compra: es la que produce el ingreso de stock. */
class CompraDetalle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id',
        'cod_prod',
        'nombre',
        'unidad',
        'cantidad',
        'precio',
        'subtotal',
        'precio_venta',
        'lote',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'cantidad'          => 'decimal:3',
        'precio'            => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'precio_venta'      => 'decimal:2',
        'fecha_vencimiento' => 'date',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'cod_prod', 'cod_prod');
    }
}
