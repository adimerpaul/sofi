<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientePhoto extends Model
{
    use SoftDeletes;

    protected $table = "cliente_photos";
    protected $fillable = [
        'cliente_id',
        'photo_path',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'Cod_Aut');
    }
}
