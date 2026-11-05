<?php

namespace App\Http\Controllers;

use App\Models\Transformacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transformacion de productos.
 *
 * Se sacan kilos de los productos de origen (p. ej. pechuga, cadera, alas a
 * granel) y se ingresan kilos de los productos que se armaron con ellos (p. ej.
 * frial mediano y grande), o al reves: un producto por kilo a otro por kilo.
 *
 * Igual que en compras, el movimiento va a tbstock (stock = SUM(cant - saldo))
 * y a tbproductos.stock_actual:
 *   ORIGEN  -> salida  (cant = 0, saldo = kg)
 *   DESTINO -> ingreso (cant = kg, saldo = 0)
 * Anular no borra filas: mete el movimiento contrario, para que el kardex
 * siga cuadrando.
 *
 * Permisos: transformacionVer, transformacionCrear, transformacionAnular.
 */
class TransformacionController extends Controller
{
    private const FECHA_NULA = '1899-11-30 04:32:36';

    private function exigir(Request $request, array $permisos)
    {
        $usuario = $request->user();
        foreach ($permisos as $p) {
            if ($usuario && $usuario->can($p)) {
                return;
            }
        }
        abort(403, 'No tiene permiso para transformaciones de productos');
    }

    /** Listado por rango de fechas, estado y producto. */
    public function index(Request $request)
    {
        $this->exigir($request, ['transformacionVer', 'transformacionCrear', 'transformacionAnular']);

        $datos = $request->validate([
            'desde'    => 'nullable|date',
            'hasta'    => 'nullable|date',
            'estado'   => 'nullable|in:ACTIVO,ANULADO',
            'cod_prod' => 'nullable|string|max:25',
        ]);
        $desde = $datos['desde'] ?? date('Y-m-d');
        $hasta = $datos['hasta'] ?? $desde;
        if ($hasta < $desde) {
            list($desde, $hasta) = [$hasta, $desde];
        }

        $lista = Transformacion::with([
            'detalles',
            'usuario:CodAut,Nombre1,App1,Apm',
            'anulador:CodAut,Nombre1,App1,Apm',
        ])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($datos['estado'] ?? null, function ($q, $estado) {
                $q->where('estado', $estado);
            })
            ->when(trim((string) ($datos['cod_prod'] ?? '')), function ($q, $cod) {
                $q->whereHas('detalles', function ($d) use ($cod) {
                    $d->where('cod_prod', $cod);
                });
            })
            ->orderByDesc('fecha')->orderByDesc('hora')->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(function ($t) {
                return $this->formato($t);
            });

        return ['desde' => $desde, 'hasta' => $hasta, 'transformaciones' => $lista];
    }

    public function show(Request $request, $id)
    {
        $this->exigir($request, ['transformacionVer', 'transformacionCrear', 'transformacionAnular']);

        $t = Transformacion::with(['detalles', 'usuario', 'anulador'])->find($id);
        abort_unless($t, 404, 'La transformación no existe');

        return $this->formato($t);
    }

    /** Busca productos para el formulario, con su stock actual en kilos. */
    public function productos(Request $request)
    {
        $this->exigir($request, ['transformacionCrear']);

        $buscar = trim((string) $request->input('buscar', ''));

        return DB::table('tbproductos')
            ->whereNull('deleted_at')
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($w) use ($buscar) {
                    $w->where('cod_prod', 'like', $buscar . '%')
                        ->orWhere('Producto', 'like', '%' . $buscar . '%');
                });
            })
            ->orderBy('Producto')
            ->limit(30)
            ->get([
                DB::raw('TRIM(cod_prod) as cod_prod'),
                DB::raw('TRIM(Producto) as producto'),
                DB::raw('TRIM(codUnid) as unidad'),
                DB::raw('TRIM(tipo) as tipo'),
                DB::raw('COALESCE(stock_actual, 0) as stock_actual'),
            ]);
    }

    /** Registra la transformacion y mueve el stock, todo en una transaccion. */
    public function store(Request $request)
    {
        $this->exigir($request, ['transformacionCrear']);

        $datos = $request->validate([
            'origen'             => 'required|array|min:1',
            'origen.*.cod_prod'  => 'required|string|max:25',
            'origen.*.kg'        => 'required|numeric|min:0.001',
            'destino'            => 'required|array|min:1',
            'destino.*.cod_prod' => 'required|string|max:25',
            'destino.*.kg'       => 'required|numeric|min:0.001',
            'destino.*.cajas'    => 'nullable|numeric|min:0',
            'observacion'        => 'nullable|string|max:255',
        ]);

        $origen = $this->agruparLineas($datos['origen']);
        $destino = $this->agruparLineas($datos['destino']);

        $repetidos = array_intersect(array_keys($origen), array_keys($destino));
        if ($repetidos) {
            return response()->json([
                'message' => 'Un producto no puede ser de origen y de destino a la vez: ' . implode(', ', $repetidos),
            ], 422);
        }

        $codigos = array_merge(array_keys($origen), array_keys($destino));
        $productos = DB::table('tbproductos')
            ->whereIn(DB::raw('TRIM(cod_prod)'), $codigos)
            ->get()
            ->keyBy(function ($p) {
                return trim($p->cod_prod);
            });
        $faltantes = array_diff($codigos, $productos->keys()->all());
        if ($faltantes) {
            return response()->json(['message' => 'No existen los productos: ' . implode(', ', $faltantes)], 422);
        }

        $usuario = $request->user();
        $ci = trim((string) ($usuario->ci ?? ''));
        if ($ci === '') {
            return response()->json(['message' => 'El usuario no tiene CI en personal'], 422);
        }

        $t = DB::transaction(function () use ($origen, $destino, $productos, $usuario, $ci, $datos) {
            $lineas = [];
            foreach (['ORIGEN' => $origen, 'DESTINO' => $destino] as $tipo => $grupo) {
                foreach ($grupo as $cod => $l) {
                    $p = $productos[$cod];
                    $lineas[] = [
                        'tipo'     => $tipo,
                        'cod_prod' => $cod,
                        'producto' => trim($p->Producto),
                        'unidad'   => trim((string) $p->codUnid) ?: null,
                        'kg'       => $l['kg'],
                        'cajas'    => $tipo === 'DESTINO' ? $l['cajas'] : null,
                        'precio'   => (float) ($p->precio_compra ?? 0),
                    ];
                }
            }

            $t = Transformacion::create([
                'user_id'          => $usuario->CodAut,
                'fecha'            => date('Y-m-d'),
                'hora'             => date('H:i:s'),
                'estado'           => 'ACTIVO',
                'observacion'      => $datos['observacion'] ?? null,
                'total_origen_kg'  => round(array_sum(array_column($origen, 'kg')), 3),
                'total_destino_kg' => round(array_sum(array_column($destino, 'kg')), 3),
            ]);

            $t->detalles()->createMany(array_map(function ($l) {
                unset($l['precio']);
                return $l;
            }, $lineas));

            $this->moverStock($lineas, $t->id, $ci, false);

            return $t;
        });

        return response()->json([
            'transformacion' => $this->formato($t->fresh(['detalles', 'usuario'])),
            'message'        => 'Transformación N° ' . $t->id . ' registrada; el stock ya fue actualizado',
        ], 201);
    }

    /** Anula y devuelve el stock: el origen vuelve a entrar y el destino sale. */
    public function anular(Request $request, $id)
    {
        $this->exigir($request, ['transformacionAnular']);

        $datos = $request->validate(['motivo' => 'required|string|max:255']);

        $usuario = $request->user();
        $ci = trim((string) ($usuario->ci ?? ''));

        $t = DB::transaction(function () use ($id, $datos, $usuario, $ci) {
            $t = Transformacion::with('detalles')->lockForUpdate()->find($id);
            abort_unless($t, 404, 'La transformación no existe');
            abort_if($t->estado === 'ANULADO', 422, 'La transformación ya estaba anulada');

            $precios = DB::table('tbproductos')
                ->whereIn(DB::raw('TRIM(cod_prod)'), $t->detalles->pluck('cod_prod')->all())
                ->pluck('precio_compra', DB::raw('TRIM(cod_prod)'));

            $lineas = $t->detalles->map(function ($d) use ($precios) {
                return [
                    'tipo'     => $d->tipo,
                    'cod_prod' => $d->cod_prod,
                    'kg'       => (float) $d->kg,
                    'precio'   => (float) ($precios[$d->cod_prod] ?? 0),
                ];
            })->all();

            $this->moverStock($lineas, $t->id, $ci, true);

            $t->update([
                'estado'           => 'ANULADO',
                'motivo_anulacion' => $datos['motivo'],
                'anulado_por'      => $usuario->CodAut,
                'anulado_at'       => now(),
            ]);

            return $t;
        });

        return response()->json([
            'message'        => 'Transformación anulada; el stock fue devuelto',
            'transformacion' => $this->formato($t->fresh(['detalles', 'usuario', 'anulador'])),
        ]);
    }

    /** Suma lineas repetidas del mismo producto. */
    private function agruparLineas(array $items): array
    {
        $res = [];
        foreach ($items as $i) {
            $cod = trim($i['cod_prod']);
            if (!isset($res[$cod])) {
                $res[$cod] = ['kg' => 0, 'cajas' => null];
            }
            $res[$cod]['kg'] = round($res[$cod]['kg'] + (float) $i['kg'], 3);
            if (isset($i['cajas']) && $i['cajas'] !== '' && $i['cajas'] !== null) {
                $res[$cod]['cajas'] = round((float) $res[$cod]['cajas'] + (float) $i['cajas'], 2);
            }
        }
        return $res;
    }

    /**
     * Escribe en tbstock y ajusta stock_actual. ORIGEN sale y DESTINO entra;
     * con $anula se invierte.
     */
    private function moverStock(array $lineas, $id, $ci, bool $anula)
    {
        $ahora = date('Y-m-d H:i:s');
        $posic = (int) DB::selectOne('SELECT COALESCE(MAX(posic), 0) AS n FROM tbstock FOR UPDATE')->n;
        $motivo = ($anula ? 'ANULA TRANSFORMACION WEB ' : 'TRANSFORMACION WEB ') . $id;

        $filas = [];
        foreach ($lineas as $l) {
            $entra = ($l['tipo'] === 'DESTINO') !== $anula;
            $filas[] = [
                'cod_prod'     => $l['cod_prod'],
                'Cod_Prodm'    => '',
                'Unidcant'     => 0,
                'UnidSaldo'    => 0,
                'cant'         => $entra ? $l['kg'] : 0,
                'saldo'        => $entra ? 0 : $l['kg'],
                'PBruto'       => 0,
                'PreUnit'      => $l['precio'],
                'CantCja'      => 0,
                'fecha'        => $ahora,
                'fecha_venc'   => self::FECHA_NULA,
                'Nro'          => 0,
                'AlmaOrig'     => 0,
                'CodStock'     => 0,
                'CodStockS'    => 0,
                'CodStockReg'  => 0,
                'ci'           => $ci,
                'MotivoEgreso' => $motivo . ($l['tipo'] === 'ORIGEN' ? ' (ORIGEN)' : ' (DESTINO)'),
                'NroLOte'      => '',
                'comandast'    => 0,
                'Nrocierre'    => 0,
                'sw'           => 0,
                'codtrans'     => 0,
                'posic'        => ++$posic,
                'motivstock'   => $motivo,
                'docum'        => '',
                'esfac'        => 0,
                'proveedor'    => '',
            ];
        }

        DB::table('tbstock')->insert($filas);

        foreach ($filas as $f) {
            $delta = round((float) $f['cant'] - (float) $f['saldo'], 3);
            if ($delta != 0) {
                DB::table('tbproductos')->whereRaw('TRIM(cod_prod) = ?', [trim($f['cod_prod'])])
                    ->update(['stock_actual' => DB::raw('COALESCE(stock_actual, 0) + (' . sprintf('%.3F', $delta) . ')')]);
            }
        }
    }

    private function nombre($u)
    {
        if (!$u) {
            return null;
        }
        $partes = array_filter(array_map('trim', [$u->Nombre1 ?? '', $u->App1 ?? '', $u->Apm ?? '']));
        return $partes ? implode(' ', $partes) : null;
    }

    private function formato(Transformacion $t): array
    {
        $linea = function ($d) {
            return [
                'cod_prod' => $d->cod_prod,
                'producto' => $d->producto,
                'unidad'   => $d->unidad,
                'kg'       => (float) $d->kg,
                'cajas'    => $d->cajas !== null ? (float) $d->cajas : null,
            ];
        };
        $origen = (float) $t->total_origen_kg;
        $destino = (float) $t->total_destino_kg;

        return [
            'id'               => $t->id,
            'fecha'            => $t->fecha ? $t->fecha->format('Y-m-d') : null,
            'hora'             => substr((string) $t->hora, 0, 8),
            'estado'           => $t->estado,
            'observacion'      => $t->observacion,
            'usuario'          => $this->nombre($t->usuario),
            'total_origen_kg'  => $origen,
            'total_destino_kg' => $destino,
            'diferencia_kg'    => round($origen - $destino, 3),
            'motivo_anulacion' => $t->motivo_anulacion,
            'anulado_por'      => $this->nombre($t->anulador),
            'anulado_at'       => $t->anulado_at ? $t->anulado_at->format('Y-m-d H:i:s') : null,
            'origen'           => $t->detalles->where('tipo', 'ORIGEN')->map($linea)->values(),
            'destino'          => $t->detalles->where('tipo', 'DESTINO')->map($linea)->values(),
        ];
    }
}
