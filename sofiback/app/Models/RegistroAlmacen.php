<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroAlmacen extends Model
{
    use HasFactory;
    protected $table = 'registro_almacenes';
    protected $fillable = [
        'cantidad',
        'fecha_vencimiento',
        'almacen_id',
        'user_id',
        'fecha_registro',
        'lote',
        'comentario',
    ];
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
}
