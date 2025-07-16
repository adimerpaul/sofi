<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisVisita extends Model
{
    use HasFactory;

    protected $table = 'misvisitas';

    protected $primaryKey = 'id';

    public $timestamps = false; // Asumiendo que no tienes `created_at` ni `updated_at`

    protected $fillable = [
        'estado',
        'fecha',
        'hora',
        'observacion',
        'lat',
        'lng',
        'distancia',
        'cliente_id',
        'personal_id',
    ];

    // Relaciones (si tienes modelos Cliente y User o Personal)
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function personal()
    {
        return $this->belongsTo(User::class, 'personal_id', 'CodAut'); // Si personal es la tabla 'personal'
    }
}
