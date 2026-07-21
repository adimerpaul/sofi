<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class CompraDetalle extends Model implements Auditable{
    use SoftDeletes, AuditableTrait;
    protected $fillable = [
        'compra_id',
        'user_id',
        'producto_id',
        'proveedor_id',
        'nombre',
        'precio',
        'cantidad',
        'cantidad_venta',
        'total',
        'factor',
        'precio13',
        'total13',
        'precio_venta',
        'estado',
        'lote',
        'fecha_vencimiento',
        'nro_factura'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];
    function compra(){
        return $this->belongsTo(Compra::class);
    }
    function user(){
        return $this->belongsTo(User::class);
    }
    function producto(){
        return $this->belongsTo(Producto::class);
    }
    function proveedor(){
        return $this->belongsTo(Proveedor::class);
    }
}
