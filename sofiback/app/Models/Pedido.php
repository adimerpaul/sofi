<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cada fila es un producto de un pedido; el pedido completo es el NroPed.
 *
 * Solo lo que se escribe por Eloquent queda en audits: crear, editar o borrar
 * filas de tbpedidos siempre por este modelo, nunca con DB::table ni SQL crudo.
 * Las consultas crudas que lean tbpedidos deben filtrar deleted_at IS NULL.
 */
class Pedido extends Model implements Auditable{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;
    protected $table="tbpedidos";
    protected $primaryKey="codAut";
    public $timestamps = false;
    protected $fillable = [
        'codAut',
        'NroPed',
        'numero_dia',
        'cod_prod',
        'CIfunc',
        'idCli',
        'Cant',
        'Tipo1',
        'Tipo2',
        'Canttxt',
        'precio',
        'fecha',
        'fecha_entrega',
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
        'comentario',
        'bonificacion',
        'bonificacionAprovacion',
        'bonificacionId',
    ];

    /**
     * La fecha de entrega es el dia siguiente al del pedido. Se recalcula
     * cada vez que cambia la fecha, salvo que la fecha de entrega venga puesta
     * a mano en el mismo guardado.
     */
    protected static function booted()
    {
        static::saving(function (Pedido $pedido) {
            if ($pedido->fecha && ($pedido->isDirty('fecha') || !$pedido->fecha_entrega) && !$pedido->isDirty('fecha_entrega')) {
                $pedido->fecha_entrega = date('Y-m-d', strtotime(substr((string) $pedido->fecha, 0, 10) . ' +1 day'));
            }
        });
    }

    function user(){
        return $this->belongsTo(User::class,'CIfunc','CodAut');
    }
    function cliente(){
        return $this->belongsTo(Cliente::class,'idCli','Cod_Aut');
    }
    function producto(){
        return $this->belongsTo(Producto::class,'cod_prod','cod_prod');
    }
    public function detalles()
    {
        return $this->hasMany(Pedido::class, 'NroPed', 'NroPed')
            ->where('NroPed', '<>', $this->NroPed);
    }
    public function bonificacionCliente(){
        return $this->belongsTo(Cliente::class,'bonificacionId','id');
    }
}
