<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Pedido extends Model implements Auditable {
    use SoftDeletes, AuditableTrait;

    protected $fillable = [
        'user_id',
        'cliente_id',
        'cliente_baja_id',
        'pedido_zona_id',
        'usuario_camion_id',
        'auxiliar_user_id',
        'venta_id',
        'fecha',
        'hora',
        'estado',
        'auxiliar_estado',
        'despacho_estado',
        'entrega_at',
        'despachador_user_id',
        'auxiliar_observacion',
        'auxiliar_hecho_at',
        'venta_generada',
        'tipo_pago',
        'facturado',
        'tipo_pedido',
        'contiene_normal',
        'contiene_res',
        'contiene_cerdo',
        'contiene_pollo',
        'fuera_de_ruta',
        'total',
        'observaciones',
        'comentario_visita'
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'facturado' => 'boolean',
        'contiene_normal' => 'boolean',
        'contiene_res' => 'boolean',
        'contiene_cerdo' => 'boolean',
        'contiene_pollo' => 'boolean',
        'fuera_de_ruta' => 'boolean',
        'pedido_zona_id' => 'integer',
        'cliente_baja_id' => 'integer',
        'usuario_camion_id' => 'integer',
        'auxiliar_user_id' => 'integer',
        'despachador_user_id' => 'integer',
        'venta_id' => 'integer',
        'auxiliar_hecho_at' => 'datetime',
        'entrega_at' => 'datetime',
        'venta_generada' => 'boolean',
    ];

    public function detalles() {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function usuarioCamion() {
        return $this->belongsTo(User::class, 'usuario_camion_id');
    }
    public function auxiliarUser() {
        return $this->belongsTo(User::class, 'auxiliar_user_id');
    }
    public function despachadorUser() {
        return $this->belongsTo(User::class, 'despachador_user_id');
    }
    public function venta() {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function cliente() {
        return $this->belongsTo(Cliente::class);
    }

    public function clienteBaja() {
        return $this->belongsTo(Cliente::class, 'cliente_baja_id');
    }

    public function zona() {
        return $this->belongsTo(PedidoZona::class, 'pedido_zona_id');
    }
    protected $appends = ['textDetalle'];
    public function getTextDetalleAttribute() {
        return $this->detalles->map(function ($detalle) {
            return $detalle->producto->nombre . ' (' . $detalle->cantidad . ')';
        })->implode(', ');
    }
}
