<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Proveedor extends Model implements Auditable{
    use SoftDeletes, AuditableTrait;
    protected $table = 'proveedores';
    protected $fillable = [
        'nombre',
        'ci',
        'telefono',
        'direccion',
        'email'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
}
