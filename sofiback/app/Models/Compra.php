<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Compra a proveedor registrada desde el sistema web.
 *
 * Es un modelo propio, pero el efecto importante ocurre fuera: cada linea
 * genera un ingreso en tbstock, que es de donde sale el stock real de Sofia.
 */
class Compra extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'compras';

    protected $fillable = [
        'user_id',
        'proveedor_id',
        'fecha',
        'hora',
        'nit',
        'proveedor',
        'nro_factura',
        'tipo_pago',
        'estado',
        'subtotal',
        'descuento',
        'total',
        'observacion',
        'motivo_anulacion',
        'anulado_at',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'subtotal'   => 'decimal:2',
        'descuento'  => 'decimal:2',
        'total'      => 'decimal:2',
        'anulado_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'CodAut');
    }

    public function proveedorRel()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id', 'CodAut');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVO');
    }
}
