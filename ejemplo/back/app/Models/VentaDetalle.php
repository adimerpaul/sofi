<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class VentaDetalle extends Model implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $fillable = [
        'venta_id',
        'producto_id',
        'cantidad',
        'unidad',
        'precio',
        'lote',
        'fecha_vencimiento',
        'nombre',
        'compra_detalle_id'
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
