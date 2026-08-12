<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoSofia extends Model
{
    use SoftDeletes;

    protected $table = 'pedidos';

    protected $fillable = [
        'nro_pedido',
        'fecha',
        'cliente_id',
        'cliente_nombre',
        'cliente_direccion',
        'cliente_telefono',
        'cliente_zona',
        'user_id',
        'user_nombre',
        'user_apellido',
        'estado',
        'fact',
        'comentario',
        'pago',
        'placa',
        'horario',
        'colorStyle',
        'cod_prod',
        'precio',
        'cantidad',
        'cantidad_texto',
        'subtotal',
        'bonificacion',
        'bonificacion_aprobacion',
        'bonificacion_id',
        'confirmado'
    ];

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class, 'pedido_id', 'nro_pedido');
    }
}
