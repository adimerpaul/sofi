<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles, AuditableTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'clave',
        'username',
        'active',
        'agencia',
        'role',
        'avatar',
        'celular',
        'es_camion',
        'cod_aut',
        'fecha_nacimiento',
        'ci',
        'cod_prof',
        'app1',
        'app2',
        'nombre1',
        'nombre2',
        'salario',
        'direccion',
        'cod_car',
        'nro',
        'nro_alm',
        'acceso_emp',
        'placa',
    ];
    protected $appends = ['color'];
    public function getColorAttribute(){
        $roles = [
            'Admin' => 'red',
            'Usuario' => 'green',
            'Cobrador' => 'teal',
        ];
        return $roles[$this->role] ?? 'blue';
    }
    protected $hidden = [
        'password',
        'clave',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'es_camion' => 'boolean',
            'fecha_nacimiento' => 'date',
            'acceso_emp' => 'boolean',
        ];
    }
}
