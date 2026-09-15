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
 *
 * En lo que se vende a granel conviven cantidad (piezas entregadas) y peso
 * (kilos de balanza); lo que se cobra es el peso, ver cantidad_facturada.
 */
class FacturaDetalle extends Model
{
    use HasFactory, SoftDeletes;

    /** Lo que pesa cada canastillo vacio con el que se pesa pollo, cerdo o res. */
    public const KG_CANASTILLO = 2;

    /** Los pedidos que se pesan en canastillos. */
    public const TIPOS_CON_CANASTILLOS = ['POLLO', 'CERDO', 'RES'];

    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id',
        'cod_prod',
        'nombre',
        'unidad',
        'cantidad',
        'cantidad_pedida',
        'peso',
        'peso_bruto',
        'canastillos',
        'precio',
        'subtotal',
    ];

    protected $casts = [
        'cantidad'        => 'decimal:3',
        'cantidad_pedida' => 'decimal:3',
        'peso'            => 'decimal:3',
        'peso_bruto'      => 'decimal:3',
        'canastillos'     => 'integer',
        'precio'   => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Lo que de verdad multiplica al precio.
     *
     * En lo que va por kilo es el peso de la balanza; en el resto, y en las
     * lineas viejas guardadas sin peso, sigue siendo la cantidad.
     */
    public function getCantidadFacturadaAttribute()
    {
        return (float) $this->peso > 0 ? (float) $this->peso : (float) $this->cantidad;
    }

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
