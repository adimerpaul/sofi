<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ProductoGrupo extends Model implements Auditable{
    use SoftDeletes, AuditableTrait;
    protected $fillable = ['nombre', 'codigo', 'producto_grupo_padre_id'];
    protected $hidden = [
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    function productoGrupoPadre(){
        return $this->belongsTo(ProductoGrupoPadre::class);
    }
}
