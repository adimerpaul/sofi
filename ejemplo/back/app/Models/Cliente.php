<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Cliente extends Model implements Auditable{
    use SoftDeletes, AuditableTrait;
    protected $fillable = [
        'nombre',
        'nit',
        'ci',
        'username',
        'telefono',
        'direccion',
        'complemento',
        'codigoTipoDocumentoIdentidad',
        'email',
        'id_externo',
        'cod_ciudad',
        'cod_nacio',
        'cod_car',
        'est_civ',
        'edad',
        'empresa',
        'categoria',
        'imp_pieza',
        'ci_vend',
        'list_blanck',
        'motivo_list_black',
        'list_black',
        'tipo_paciente',
        'supra_canal',
        'canal',
        'subcanal',
        'zona',
        'latitud',
        'longitud',
        'transporte',
        'territorio',
        'codcli',
        'clinew',
        'venta_estado',
        'complto',
        'tipodocu',
        'lu',
        'ma',
        'mi',
        'ju',
        'vi',
        'sa',
        'do',
        'correcli',
        'canmayni',
        'baja',
        'profecion',
        'waths',
        'ctas_activo',
        'ctas_mont',
        'ctas_dias',
        'sexo',
        'noesempre',
        'tarjeta',
        'puede_credito',
        'fotos',
    ];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'fotos' => 'array',
            'latitud' => 'float',
            'longitud' => 'float',
            'list_blanck' => 'boolean',
            'list_black' => 'boolean',
            'lu' => 'boolean',
            'ma' => 'boolean',
            'mi' => 'boolean',
            'ju' => 'boolean',
            'vi' => 'boolean',
            'sa' => 'boolean',
            'do' => 'boolean',
            'canmayni' => 'boolean',
            'baja' => 'boolean',
            'waths' => 'boolean',
            'ctas_activo' => 'boolean',
            'noesempre' => 'boolean',
            'puede_credito' => 'boolean',
        ];
    }

    public function vendedorUser()
    {
        return $this->belongsTo(User::class, 'ci_vend', 'username');
    }
}
