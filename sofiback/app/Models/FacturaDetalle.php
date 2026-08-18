<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Linea de una factura.
 *
 * El nombre, la unidad y el precio quedan copiados al guardar para que la
 * factura se pueda reimprimir tal cual aunque el producto cambie despues.
 */
class FacturaDetalle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id',
        'cod_prod',
        'nombre',
        'unidad',
        'cantidad',
        'precio',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio'   => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    /** tbproductos se referencia por cod_prod, que es su clave real. */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'cod_prod', 'cod_prod');
    }
}
