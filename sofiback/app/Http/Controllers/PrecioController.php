<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Services\TipoPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de precios: los 13 precios de cada producto y su precio de compra en
 * una sola grilla, como la pantalla "Cambio de precios" del sistema anterior.
 *
 * Precio es el precio publico (el de venta) y Precio_Costo el "2do precio";
 * Precio3..Precio13 son las demas listas. precio_compra es lo que cuesta.
 */
class PrecioController extends Controller
{
    /** Los 13 precios en el orden de la grilla. */
    const PRECIOS = [
        'Precio', 'Precio_Costo', 'Precio3', 'Precio4', 'Precio5', 'Precio6', 'Precio7',
        'Precio8', 'Precio9', 'Precio10', 'Precio11', 'Precio12', 'Precio13',
    ];

    /** Todos los productos con sus precios; la grilla busca y filtra en el navegador. */
    public function index()
    {
        $productos = DB::table('tbproductos')
            ->orderBy('cod_prod')
            ->get(array_merge(
                [DB::raw('TRIM(cod_prod) as cod_prod'), DB::raw('TRIM(Producto) as producto'),
                    DB::raw('TRIM(codUnid) as unidad'), DB::raw('UPPER(TRIM(tipo)) as tipo'), 'precio_compra'],
                self::PRECIOS
            ));

        return $productos->map(function ($p) {
            foreach (self::PRECIOS as $campo) {
                $p->$campo = round((float) $p->$campo, 3);
            }
            $p->precio_compra = $p->precio_compra !== null ? round((float) $p->precio_compra, 2) : null;
            $p->inactivo = stripos($p->producto, 'INACTIVO') === 0;
            return $p;
        });
    }

    /**
     * Guarda de una vez todo lo que se cambio en la grilla: solo llegan los
     * productos tocados y, de cada uno, los precios que cambiaron.
     */
    public function guardar(Request $request)
    {
        if (!$request->user()->can('preciosActualizar')) {
            return response()->json(['message' => 'No tiene permiso para cambiar precios'], 403);
        }

        $reglas = [
            'cambios' => 'required|array|min:1',
            'cambios.*.cod_prod' => 'required|string|max:25',
            'cambios.*.precio_compra' => 'nullable|numeric|min:0',
        ];
        foreach (self::PRECIOS as $campo) {
            $reglas['cambios.*.' . $campo] = 'nullable|numeric|min:0';
        }
        $datos = $request->validate($reglas);

        $guardados = 0;
        $noExisten = [];
        $lineasPedido = 0;

        DB::transaction(function () use ($datos, &$guardados, &$noExisten, &$lineasPedido) {
            foreach ($datos['cambios'] as $cambio) {
                $codigo = trim($cambio['cod_prod']);
                $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [$codigo])->first();
                if (!$producto) {
                    $noExisten[] = $codigo;
                    continue;
                }
                $antes = $producto->only(self::PRECIOS);

                foreach (self::PRECIOS as $campo) {
                    if (array_key_exists($campo, $cambio)) {
                        // Las columnas de precio son NOT NULL: vacio es 0.
                        $producto->$campo = round((float) ($cambio[$campo] ?? 0), 3);
                    }
                }
                if (array_key_exists('precio_compra', $cambio)) {
                    // Vacio = sin precio de compra cargado.
                    $producto->precio_compra = $cambio['precio_compra'] === null
                        ? null : round((float) $cambio['precio_compra'], 2);
                }

                if ($producto->isDirty()) {
                    $producto->save();
                    $guardados++;
                    $lineasPedido += $this->actualizarPedidos($codigo, $antes, $producto->only(self::PRECIOS));
                }
            }
        });

        return [
            'guardados' => $guardados,
            'no_existen' => $noExisten,
            'lineas_pedido' => $lineasPedido,
            'message' => ($guardados === 1
                ? 'Se actualizaron los precios de 1 producto'
                : 'Se actualizaron los precios de ' . $guardados . ' productos')
                . ($lineasPedido ? ' y ' . $lineasPedido . ' línea(s) de pedidos sin facturar' : ''),
        ];
    }

    /**
     * Lleva el precio nuevo a los pedidos que todavia no se facturaron.
     *
     * La linea del pedido guarda un solo precio y no dice de que lista salio:
     * se reconoce por el valor. Si tenia el valor viejo del Precio N pasa al
     * nuevo del Precio N. Un valor que estaba en dos listas que ahora quedan
     * distintas no se sabe a cual seguir y no se toca; tampoco un precio
     * puesto a mano que no coincide con ninguna lista.
     *
     * Solo pedidos validos: no borrados, sin bonificacion, que salen hoy o
     * despues y sin comprobante vigente (lo facturado ya se cobro asi).
     */
    private function actualizarPedidos($codigo, array $antes, array $despues)
    {
        // valor viejo (2 decimales, como en tbpedidos) => valores nuevos
        $destinos = [];
        foreach (self::PRECIOS as $campo) {
            $viejo = number_format((float) $antes[$campo], 2, '.', '');
            $destinos[$viejo][number_format((float) $despues[$campo], 2, '.', '')] = true;
        }

        $tipo = TipoPedido::sql('p');
        $lineas = 0;
        foreach ($destinos as $viejo => $nuevos) {
            $viejo = (string) $viejo;
            if (count($nuevos) !== 1 || (float) $viejo <= 0) {
                continue;
            }
            $nuevo = (string) array_keys($nuevos)[0];
            if ($nuevo === $viejo) {
                continue;
            }
            $lineas += DB::update("
                UPDATE tbpedidos p
                SET p.precio = ?, p.subtotal = ROUND(p.Cant * ?, 2)
                WHERE p.deleted_at IS NULL
                  AND TRIM(p.cod_prod) = ?
                  AND ROUND(p.precio, 2) = ?
                  AND COALESCE(p.bonificacion, 0) = 0
                  AND p.fecha_entrega >= ?
                  AND NOT EXISTS (
                      SELECT 1 FROM facturas f
                      WHERE f.deleted_at IS NULL AND f.estado <> 'ANULADO'
                        AND f.pedido_nro = p.NroPed
                        AND UPPER(TRIM(f.pedido_tipo)) = {$tipo}
                  )
            ", [$nuevo, $nuevo, $codigo, $viejo, date('Y-m-d')]);
        }

        return $lineas;
    }
}
