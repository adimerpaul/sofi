<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Venta o factura registrada desde el sistema web.
 *
 * Modelo propio, aparte de tbventas (que es del sistema de caja). Las
 * relaciones apuntan a las tablas legadas que ya son maestro de cada cosa:
 * el cliente a tbclientes y el usuario a personal.
 */
class Factura extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facturas';

    protected $fillable = [
        'user_id',
        'cliente_id',
        'vendedor_ci',
        'fecha',
        'hora',
        'nit',
        'nombre',
        'tipo_comprobante',
        'tipo_pago',
        'estado',
        'subtotal',
        'descuento',
        'total',
        'observacion',
        'pedido_nro',
        'pedido_tipo',
        'nro_factura',
        'codigo_sucursal',
        'codigo_punto_venta',
        'cuf',
        'cufd',
        'codigo_control',
        'codigo_recepcion',
        'estado_siat',
        'mensaje_siat',
        'fecha_emision',
        'xml',
        'leyenda',
        'online',
        'motivo_anulacion',
        'anulado_at',
    ];

    /** El XML enviado al SIAT pesa varios KB y no lo usa ninguna pantalla. */
    protected $hidden = ['xml'];

    protected $casts = [
        'fecha'      => 'date',
        'subtotal'   => 'decimal:2',
        'descuento'  => 'decimal:2',
        'total'      => 'decimal:2',
        'online'     => 'boolean',
        'anulado_at' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'Cod_Aut');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id', 'CodAut');
    }

    /** El vendedor se enlaza por CI, que es como lo guarda tbclientes. */
    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_ci', 'ci');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVO');
    }
}
