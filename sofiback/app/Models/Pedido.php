<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model{
    use HasFactory;
    protected $table="tbpedidos";
    protected $primaryKey="codAut";
    public $timestamps = false;
    protected $fillable = [
        'codAut',
        'NroPed',
        'cod_prod',
        'CIfunc',
        'idCli',
        'Cant',
        'Tipo1',
        'Tipo2',
        'Canttxt',
        'precio',
        'fecha',
        'estado',
        'estados',
        'impreso',
        'Observaciones',
        'pagado',
        'subtotal',
        'cbrasa5',
        'ubrasa5',
        'cbrasa6',
        'cubrasa6',
        'c104',
        'u104',
        'c105',
        'u105',
        'c106',
        'u106',
        'c107',
        'u107',
        'c108',
        'u108',
        'c109',
        'u109',
        'rango',
        'ala',
        'unidala',
        'cadera',
        'unidcadera',
        'pecho',
        'unidpecho',
        'pie',
        'unidpie',
        'filete',
        'unidfilete',
        'cuello',
        'unidcuello',
        'hueso',
        'unidhueso',
        'menu',
        'unidmenu',
        'bs',
        'bs2',
        'contado',
        'tipo',
        'total',
        'entero',
        'desmembre',
        'corte',
        'kilo',
        'trozado',
        'pierna',
        'brazo',
        'hora',
        'pago',
        'bsala',
        'obsala',
        'bscadera',
        'obscadera',
        'bspecho',
        'obspecho',
        'bspie',
        'obspie',
        'bsfilete',
        'obsfilete',
        'bscuello',
        'obscuello',
        'bshueso',
        'obshueso',
        'bsmenu',
        'obsmenu',
        'bs104',
        'obs104',
        'bs105',
        'obs105',
        'bs106',
        'obs106',
        'bs107',
        'obs107',
        'bs108',
        'obs108',
        'bs109',
        'obs109',
        'bsbrasa5',
        'obsbrasa5',
        'bsbrasa6',
        'obsbrasa6',
        'pfrial',
        'fact',
        'Impreso2',
        'horario',
        'comentario'
    ];

    function user(){
        return $this->belongsTo(User::class,'CIfunc','CodAut');
    }
    function cliente(){
        return $this->belongsTo(Cliente::class,'idCli','Cod_Aut');
    }
    function producto()
    {
        return $this->belongsTo(Producto::class, 'cod_prod', 'cod_prod')
            ->whereRaw('TRIM(productos.cod_prod) = TRIM(?)', [$this->cod_prod]);
    }
    public function detalles()
    {
        return $this->hasMany(Pedido::class, 'NroPed', 'NroPed')
            ->where('NroPed', '<>', $this->NroPed);
    }
}
