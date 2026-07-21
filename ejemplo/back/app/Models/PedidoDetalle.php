<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class PedidoDetalle extends Model implements Auditable {
    use SoftDeletes, AuditableTrait;

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'cantidad',
        'precio',
        'total',
        'observacion_detalle',
        'detalle_extra',
    ];

    protected $casts = [
        'detalle_extra' => 'array',
    ];

    public function producto() {
        return $this->belongsTo(Producto::class);
    }
}
