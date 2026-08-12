<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Producto extends Model implements Auditable{
    use SoftDeletes, AuditableTrait;

    protected $fillable = [
        'codigo',
        'imagen',
        'producto_grupo_id',
        'producto_grupo_padre_id',
        'nombre',
        'tipo_producto',
        'precio1',
        'precio2',
        'precio3',
        'precio4',
        'precio5',
        'precio6',
        'precio7',
        'precio8',
        'precio9',
        'precio10',
        'precio11',
        'precio12',
        'precio13',
        'codigo_unidad',
        'unidades_caja',
        'cantidad_presentacion',
        'peso_estimado',
        'tipo',
        'oferta',
        'codigo_producto_sin',
        'presentacion',
        'codigo_grupo_sin',
        'credito',
        'active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Keep compatibility with existing front code that still reads "precio" and "barra".
    protected $appends = ['stock', 'precio', 'barra'];

    function comprasDetalles(){
        return $this->hasMany(CompraDetalle::class);
    }

    function productoGrupo(){
        return $this->belongsTo(ProductoGrupo::class);
    }

    function productoGrupoPadre(){
        return $this->belongsTo(ProductoGrupoPadre::class);
    }

    public function getPrecioAttribute(): float
    {
        return (float) ($this->precio1 ?? 0);
    }

    public function setPrecioAttribute($value): void
    {
        $this->attributes['precio1'] = $value;
    }

    public function getBarraAttribute(): ?string
    {
        return $this->codigo;
    }

    public function setBarraAttribute($value): void
    {
        $this->attributes['codigo'] = $value;
    }

    public function getStockAttribute($value)
    {
        if ($value !== null) {
            return (float) $value;
        }

        return (float) $this->comprasDetalles()
            ->where('estado', 'Activo')
            ->whereNull('compra_detalles.deleted_at')
            ->sum('cantidad_venta');
    }
}
