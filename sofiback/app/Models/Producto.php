<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model{
    use HasFactory;
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
        'PreCosto',
        'stock',
        'Imprime',
        'codUnid',
        'UnidCja',
        'CantPren',
        'Peso',
        'tipo',
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
