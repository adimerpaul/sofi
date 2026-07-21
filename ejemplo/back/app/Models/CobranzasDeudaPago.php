<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CobranzasDeudaPago extends Model implements Auditable
{
    use AuditableTrait;
    protected $table = 'cobranzas_deuda_pagos';

    protected $fillable = [
        'deuda_id',
        'user_id',
        'anulado_user_id',
        'monto',
        'estado',
        'fecha_hora',
        'anulado_at',
        'metodo_pago',
        'considerar_en_cobranza',
        'nro_pago',
        'observacion',
        'comprobante_path',
    ];

    protected $casts = [
        'monto' => 'float',
        'estado' => 'string',
        'fecha_hora' => 'datetime',
        'anulado_at' => 'datetime',
        'considerar_en_cobranza' => 'boolean',
    ];

    public function deuda()
    {
        return $this->belongsTo(CobranzasDeuda::class, 'deuda_id');
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
