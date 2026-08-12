<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encuesta extends Model
{
    use SoftDeletes;

    protected $table = 'encuestas';

    protected $fillable = [
        'cliente_cod_aut','usuario_cod_aut',
        'cliente_id','cliente_nombre','cliente_tel','cliente_dir','cliente_zona','cliente_lat','cliente_lng',
        'usuario_ci','usuario_nombre','usuario_correo','usuario_placa',
        'score','comment','encuesta_date','email',
        'client_ip','origin_scheme','origin_host','origin_path','server_ip','user_agent','referer',
    ];
    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at'
    ];
}
