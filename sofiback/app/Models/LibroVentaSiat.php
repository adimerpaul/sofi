<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fila del libro de ventas del SIAT importado desde Excel.
 * Ver App\Services\LibroVentasSiatImporter y VerificacionFacturasController.
 */
class LibroVentaSiat extends Model
{
    use SoftDeletes;

    protected $table = 'libro_ventas_siat';

    protected $fillable = [
        'nro', 'fecha_factura', 'numero_factura', 'cuf', 'nit_ci_cliente', 'complemento', 'razon_social',
        'importe_total', 'importe_ice', 'importe_iehd', 'importe_ipj', 'tasas', 'otros_no_sujetos_iva',
        'exportaciones_exentas', 'ventas_tasa_cero', 'subtotal', 'descuentos', 'importe_gift_card',
        'importe_base_debito_fiscal', 'debito_fiscal', 'estado', 'codigo_control', 'tipo_venta',
        'credito_fiscal', 'estado_consolidacion', 'archivo_origen', 'user_id',
    ];

    protected $casts = [
        'fecha_factura' => 'date:Y-m-d',
        'importe_total' => 'decimal:2',
        'importe_ice' => 'decimal:2',
        'importe_iehd' => 'decimal:2',
        'importe_ipj' => 'decimal:2',
        'tasas' => 'decimal:2',
        'otros_no_sujetos_iva' => 'decimal:2',
        'exportaciones_exentas' => 'decimal:2',
        'ventas_tasa_cero' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuentos' => 'decimal:2',
        'importe_gift_card' => 'decimal:2',
        'importe_base_debito_fiscal' => 'decimal:2',
        'debito_fiscal' => 'decimal:2',
    ];

    /** La factura de Sofia que emitio este CUF (facturacion normal). */
    public function factura()
    {
        return $this->hasOne(Factura::class, 'cuf', 'cuf');
    }

    /** O la factura ficticia que lo emitio. */
    public function ficticia()
    {
        return $this->hasOne(FacturaFicticia::class, 'cuf', 'cuf');
    }
}
