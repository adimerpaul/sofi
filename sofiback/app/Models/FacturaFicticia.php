<?php

namespace App\Models;

/**
 * Factura ficticia: se emite al SIAT pero no es una venta del sistema.
 *
 * No descuenta inventario ni entra a camiones, entregas o cobranzas; vive en
 * su propia tabla. Hereda de Factura solo para que SiatService la pueda
 * emitir, verificar y anular sin cambios: las columnas fiscales son las mismas.
 */
class FacturaFicticia extends Factura
{
    protected $table = 'facturas_ficticias';

    protected $fillable = [
        'user_id',
        'cliente_id',
        'fecha',
        'hora',
        'nit',
        'nombre',
        'tipo_pago',
        'estado',
        'subtotal',
        'descuento',
        'total',
        'observacion',
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
        'anulacion_observacion',
        'anulado_at',
        'anulado_por',
    ];

    /** SiatService arma el XML con estas lineas, igual que con las de Factura. */
    public function detalles()
    {
        return $this->hasMany(FacturaFicticiaDetalle::class, 'factura_ficticia_id');
    }
}
