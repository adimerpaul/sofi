<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Producto del catalogo (tbproductos).
 *
 * Eliminar es borrado logico: deleted_at, y quien lo hizo queda en audits.
 * Mientras esta eliminado el nombre lleva el sufijo INACTIVO, que es lo que
 * filtran las consultas crudas; restaurar quita las dos marcas. Las
 * relaciones de ventas, pedidos y compras lo traen aunque este eliminado.
 */
class Producto extends Model implements Auditable{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;
    protected $table="tbproductos";
    protected $primaryKey="CodAut";
    public $timestamps = false;
    protected $fillable = [
        'CodAut',
        'cod_prod',
        'cod_grup',
        'cod_pdr',
        'Producto',
        'Nomcomer',
        'TipPro',
        'Precio',
        'Precio_Costo',
        'Precio3',
        'Precio4',
        'Precio5',
        'Precio6',
        'Precio7',
        'Precio8',
        'Precio9',
        'Precio10',
        'Precio11',
        'Precio12',
        'Precio13',
        'precioAprox',
        'PreCosto',
        'stock',
        'Imprime',
        'codUnid',
        'UnidCja',
        'CantPren',
        'Peso',
        'tipo',
        'tipo_producto_id',
        'imagen',
        'trozado',
        'oferta',
        'codProdSin',
        'pqsiramento',
        'codgruppasin',
        'credit',
    ];
    public function stock()
    {
        return $this->hasMany(Stock::class, 'cod_prod', 'cod_prod');
    }
}
