<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table="tbclientes";
    protected $primaryKey="Cod_Aut";
    public $timestamps = false;
    protected $fillable = [
        'Cod_Aut',
        'Id',
        'Cod_ciudad',
        'Cod_Nacio',
        'cod_car',
        'Nombres',
        'Telf',
        'Direccion',
        'EstCiv',
        'edad',
        'Empresa',
        'Categoria',
        'Imp_pieza',
        'CiVend',
        'ListBlanck',
        'MotivoListBlack',
        'ListBlack',
        'TipoPaciente',
        'SupraCanal',
        'Canal',
        'subcanal',
        'zona',
        'Latitud',
        'longitud',
        'transporte',
        'territorio',
        'codcli',
        'clinew',
        'venta',
        'complto',
        'Tipodocu',
        'lu',
        'Ma',
        'Mi',
        'Ju',
        'Vi',
        'Sa',
        'do',
        'Correcli',
        'canmayni',
        'baja',
        'profecion',
        'waths',
        'ctasActivo',
        'ctasMont',
        'ctasdias',
        'sexo',
    ];
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'idCli', 'Cod_Aut');
    }
    public function visitas()
    {
        return $this->hasMany(Misvisita::class, 'cliente_id', 'Cod_Aut');
    }

    public function ultimaVisita()
    {
        return $this->hasOne(Misvisita::class, 'cliente_id', 'Cod_Aut')->latestOfMany();
    }
}
