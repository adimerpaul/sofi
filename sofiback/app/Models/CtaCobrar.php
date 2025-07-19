<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtaCobrar extends Model
{
    use HasFactory;

    protected $table = 'tbctascobrar';
    protected $primaryKey = 'CodAuto';
    public $timestamps = false;

    protected $fillable = [
        'comanda',
        'CINIT',
        'CIFunc',
        'CiCajero',
        'Importe',
        'Acuenta',
        'Nrocierre',
        'FechaCan',
        'Nroficha',
        'FechaEntreg',
        'codcli',
        'fechaof',
        'placa',
        'Tipago',
        'Observacion',
        'Observacio',
        'imprimio',
    ];

    // Relaciones (opcional si tienes los modelos correspondientes)
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'codcli', 'Cod_Aut');
    }

    public function preventista()
    {
        return $this->belongsTo(User::class, 'CIFunc', 'CodAut');
    }

    public function cajero()
    {
        return $this->belongsTo(User::class, 'CiCajero', 'CodAut');
    }
}
