<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasFactory;
    protected $table = 'almacenes';
    protected $fillable = [
        'codigo',
        'codigo_producto',
        'producto',
        'unidad',
        'saldo',
        'registro',
        'vencimiento',
        'grupo',
        'fecha_registro',
        'se_descargo'
    ];
}
