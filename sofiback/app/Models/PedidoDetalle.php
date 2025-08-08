<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoDetalle extends Model
{
    use SoftDeletes;

    protected $table = 'pedido_detalles';

    protected $fillable = [
        'pedido_id',
        'cod_prod',
        'nombre',
        'precio',
        'cantidad',
        'peso',
        'cantidad_texto',
        'subtotal',
    ];


    public function pedido()
    {
        return $this->belongsTo(PedidoSofia::class, 'pedido_id', 'nro_pedido');
    }
}
