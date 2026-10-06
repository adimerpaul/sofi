<?php

namespace App\Http\Controllers;

use App\Models\Producto;
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

        DB::transaction(function () use ($datos, &$guardados, &$noExisten) {
            foreach ($datos['cambios'] as $cambio) {
                $codigo = trim($cambio['cod_prod']);
                $producto = Producto::whereRaw('TRIM(cod_prod) = ?', [$codigo])->first();
                if (!$producto) {
                    $noExisten[] = $codigo;
                    continue;
                }

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
                }
            }
        });

        return [
            'guardados' => $guardados,
            'no_existen' => $noExisten,
            'message' => $guardados === 1
                ? 'Se actualizaron los precios de 1 producto'
                : 'Se actualizaron los precios de ' . $guardados . ' productos',
        ];
    }
}
