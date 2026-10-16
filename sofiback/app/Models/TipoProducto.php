<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de producto administrable (POLLO, EMBUTIDO, HUEVO...).
 *
 * modo es el formulario con que se pide (NORMAL, POLLO, CERDO o RES) y es lo
 * que se copia a tbproductos.tipo al asignarlo a un producto.
 */
class TipoProducto extends Model
{
    const MODOS = ['NORMAL', 'POLLO', 'CERDO', 'RES'];

    protected $table = 'tipos_producto';
    protected $fillable = ['nombre', 'modo', 'color', 'orden'];
}
