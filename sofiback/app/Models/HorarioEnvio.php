<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioEnvio extends Model
{
    use HasFactory;

    protected $table = 'tbhorariosenvio';

    protected $fillable = [
        'hora',
        'dias',
        'activo',
        'ultima_ejecucion',
        'pedidos_enviados',
    ];
}
