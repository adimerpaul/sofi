<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Visita extends Model implements Auditable
{
    use AuditableTrait;
    protected $fillable = [
        'user_id',
        'cliente_id',
        'fecha',
        'hora',
        'tipo_visita',
        'comentario',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
