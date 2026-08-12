<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Subcategoria extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;
    protected $table = 'subcategorias';
    protected $fillable = [
        'nombre',
        'descripcion',
        'categoria_id'
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
