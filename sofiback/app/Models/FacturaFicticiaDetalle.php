<?php

namespace App\Models;

/**
 * Linea de una factura ficticia. Sin peso ni canastillos: lo que se cobra es
 * la cantidad, y cantidad_facturada (heredado) la devuelve tal cual.
 */
class FacturaFicticiaDetalle extends FacturaDetalle
{
    protected $table = 'factura_ficticia_detalles';

    protected $fillable = [
        'factura_ficticia_id',
        'cod_prod',
        'nombre',
        'unidad',
        'cantidad',
        'precio',
        'subtotal',
    ];

    public function factura()
    {
        return $this->belongsTo(FacturaFicticia::class, 'factura_ficticia_id');
    }
}
