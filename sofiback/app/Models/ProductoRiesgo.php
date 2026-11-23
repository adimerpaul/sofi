<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Producto en riesgo (por vencer), copiado de un conteo de inventario.
 * Ver la migracion create_productos_riesgo.
 */
class ProductoRiesgo extends Model
{
    protected $table = 'productos_riesgo';

    protected $fillable = [
        'generacion', 'dias', 'observacion',
        'almacen_id', 'registro_almacen_id', 'fecha_inventario', 'codigo', 'codigo_producto', 'producto',
        'unidad', 'grupo', 'lote', 'fecha_vencimiento', 'cantidad', 'cantidad_original', 'fecha_vencimiento_original',
        'comentario', 'contado_por', 'estado', 'editado_por', 'editado_por_nombre', 'editado_at',
        'agregado_por', 'agregado_por_nombre', 'agregado_at',
        'quitado_por', 'quitado_por_nombre', 'quitado_at', 'motivo_quitado',
    ];

    /** Numero de la ultima vez que se lleno la lista (la que se muestra). */
    public static function generacionActual()
    {
        return self::max('generacion');
    }

    public static function nombreUsuario($user)
    {
        if (!$user) return '';
        return trim(trim((string) $user->Nombre1) . ' ' . trim((string) $user->App1));
    }
}
