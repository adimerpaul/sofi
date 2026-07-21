<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ImpuestoFalla extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'impuesto_fallas';

    protected $fillable = [
        'tipo',
        'mensaje',
        'detalle',
        'estado',
        'fecha_evento',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_evento' => 'datetime',
            'resolved_at' => 'datetime',
            'detalle' => 'array',
        ];
    }
}
