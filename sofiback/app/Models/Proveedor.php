<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Proveedor del sistema legado (tbproveedor). */
class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'tbproveedor';
    protected $primaryKey = 'CodAut';
    public $timestamps = false;

    protected $fillable = [
        'NIT',
        'PROVEEDOR',
        'DIRECCION',
        'TELF',
    ];
}
