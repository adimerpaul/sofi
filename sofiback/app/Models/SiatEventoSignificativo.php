<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Evento significativo registrado en el SIAT. Ver SiatService::enviarPorEvento. */
class SiatEventoSignificativo extends Model
{
    protected $table = 'siat_eventos_significativos';

    protected $fillable = [
        'codigo_motivo', 'descripcion', 'inicio', 'fin', 'cufd', 'cufd_evento',
        'codigo_sucursal', 'codigo_punto_venta', 'codigo_evento', 'codigo_recepcion',
        'estado', 'mensaje', 'factura_ids', 'user_id',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fin' => 'datetime',
        'factura_ids' => 'array',
    ];
}
