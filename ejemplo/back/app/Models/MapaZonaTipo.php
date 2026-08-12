<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class MapaZonaTipo extends Model implements Auditable
{
    use AuditableTrait;

    protected $table = 'mapa_zona_tipos';

    protected $fillable = [
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'nombre' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
}
