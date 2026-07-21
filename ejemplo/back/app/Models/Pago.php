<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Pago extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $fillable = [
        'venta_id',
        'pedido_id',
        'cliente_id',
        'user_id',
        'anulado_user_id',
        'tipo_pago',
        'metodo_pago',
        'monto',
        'correcciones',
        'estado',
        'fecha_hora',
        'anulado_at',
        'observacion',
        'considerar_en_cobranza',
        'nro_pago',
        'comprobante_path',
        'latitud',
        'longitud',
    ];

    protected $casts = [
        'monto' => 'float',
        'correcciones' => 'integer',
        'estado' => 'string',
        'fecha_hora' => 'datetime',
        'anulado_at' => 'datetime',
        'deleted_at' => 'datetime',
        'considerar_en_cobranza' => 'boolean',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function anuladoPor()
    {
        return $this->belongsTo(User::class, 'anulado_user_id');
    }
}
