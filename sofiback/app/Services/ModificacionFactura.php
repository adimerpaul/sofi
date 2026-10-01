<?php

namespace App\Services;

use App\Models\Factura;

/**
 * Historial de ediciones de un comprobante.
 *
 * Editar un comprobante es anularlo y emitir otro desde el mismo pedido. El
 * nuevo queda enganchado a la cadena con tres datos:
 *  - modificacion_padre_id: el primer comprobante de la cadena (el original).
 *  - modificacion_nro: que modificacion es, contando desde el padre (1, 2, 3...).
 *  - modificacion_campos: que cambio respecto del inmediato anterior.
 */
class ModificacionFactura
{
    /** Datos de cabecera que se comparan entre una version y la siguiente. */
    private const CABECERA = [
        'tipo_comprobante' => 'Comprobante',
        'tipo_pago'        => 'Forma de pago',
        'nit'              => 'NIT/CI',
        'nombre'           => 'Nombre',
        'observacion'      => 'Observación',
        'descuento'        => 'Descuento',
        'total'            => 'Total',
    ];

    /** Datos de cada producto que se comparan. */
    private const DETALLE = [
        'cantidad'    => 'Cantidad',
        'peso'        => 'Peso',
        'peso_bruto'  => 'P. bruto',
        'canastillos' => 'Canastillos',
        'precio'      => 'Precio',
        'subtotal'    => 'Subtotal',
    ];

    /**
     * El comprobante anterior de la cadena: la ultima venta anulada del mismo
     * pedido emitida antes que esta. Null si es la primera.
     */
    public function anterior(Factura $factura): ?Factura
    {
        if (!$factura->pedido_nro) {
            return null;
        }

        return Factura::with('detalles')
            ->where('pedido_nro', $factura->pedido_nro)
            ->where('pedido_tipo', $factura->pedido_tipo)
            ->where('estado', 'ANULADO')
            ->where('id', '<', $factura->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Engancha el comprobante a su cadena y guarda que cambio. No hace nada si
     * no viene de una edicion.
     */
    public function registrar(Factura $factura): void
    {
        $anterior = $this->anterior($factura);
        if (!$anterior) {
            return;
        }

        $factura->loadMissing('detalles');

        $factura->forceFill([
            'modificacion_padre_id' => $anterior->modificacion_padre_id ?: $anterior->id,
            'modificacion_nro'      => ((int) $anterior->modificacion_nro) + 1,
            // El modelo lo guarda como JSON (cast array).
            'modificacion_campos'   => $this->diferencias($anterior, $factura),
        ])->save();
    }

    /** Lo que cambio de una version a la siguiente. */
    public function diferencias(Factura $antes, Factura $despues): array
    {
        $cabecera = [];
        foreach (self::CABECERA as $campo => $etiqueta) {
            $a = $this->valor($antes->{$campo});
            $d = $this->valor($despues->{$campo});
            if ($a !== $d) {
                $cabecera[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'antes' => $a, 'despues' => $d];
            }
        }

        $porCodigo = function ($detalles) {
            return collect($detalles)->keyBy(function ($d) {
                return trim((string) $d->cod_prod);
            });
        };
        $lineasAntes = $porCodigo($antes->detalles);
        $lineasDespues = $porCodigo($despues->detalles);

        $productos = [];
        foreach ($lineasDespues as $codigo => $linea) {
            $previa = $lineasAntes->get($codigo);
            if (!$previa) {
                $productos[] = [
                    'cod_prod' => (string) $codigo, 'nombre' => $linea->nombre, 'cambio' => 'AGREGADO',
                    'campos' => [['campo' => 'subtotal', 'etiqueta' => 'Subtotal', 'antes' => null,
                        'despues' => $this->valor($linea->subtotal)]],
                ];
                continue;
            }
            $campos = [];
            foreach (self::DETALLE as $campo => $etiqueta) {
                $a = $this->valor($previa->{$campo});
                $d = $this->valor($linea->{$campo});
                if ($a !== $d) {
                    $campos[] = ['campo' => $campo, 'etiqueta' => $etiqueta, 'antes' => $a, 'despues' => $d];
                }
            }
            if ($campos) {
                $productos[] = ['cod_prod' => (string) $codigo, 'nombre' => $linea->nombre, 'cambio' => 'MODIFICADO', 'campos' => $campos];
            }
        }
        foreach ($lineasAntes as $codigo => $previa) {
            if (!$lineasDespues->has($codigo)) {
                $productos[] = [
                    'cod_prod' => (string) $codigo, 'nombre' => $previa->nombre, 'cambio' => 'QUITADO',
                    'campos' => [['campo' => 'subtotal', 'etiqueta' => 'Subtotal',
                        'antes' => $this->valor($previa->subtotal), 'despues' => null]],
                ];
            }
        }

        // Una linea por cambio, para mostrar sin abrir el detalle.
        $resumen = array_map(function ($c) {
            return $c['etiqueta'];
        }, $cabecera);
        foreach ($productos as $p) {
            $resumen[] = trim($p['nombre']) . ': ' . ($p['cambio'] === 'MODIFICADO'
                ? implode(', ', array_map(function ($c) {
                    return mb_strtolower($c['etiqueta']);
                }, $p['campos']))
                : mb_strtolower($p['cambio']));
        }

        return [
            'anterior_id' => $antes->id,
            'cabecera'    => $cabecera,
            'productos'   => $productos,
            'resumen'     => $resumen,
        ];
    }

    /** Normaliza para comparar: numeros con dos o tres decimales, texto recortado. */
    private function valor($valor)
    {
        if ($valor === null) {
            return null;
        }
        if (is_numeric($valor)) {
            $numero = round((float) $valor, 3);
            return $numero == 0 && (string) $valor === '' ? null : $numero;
        }
        $texto = trim((string) $valor);
        return $texto === '' ? null : $texto;
    }
}
