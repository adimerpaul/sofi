<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class CompraController extends Controller{
    function historialCompras($id){
        $historial = \App\Models\CompraDetalle::with('compra')
            ->where('producto_id', $id)
            ->orderByDesc('fecha_vencimiento')
            ->get();

        $historial = $historial->map(function ($item) {
            $cantidad = (float) ($item->cantidad ?? 0);
            $disponible = (float) ($item->cantidad_venta ?? 0);
            $vendida = max(0, $cantidad - $disponible);
            $item->cantidad_vendida = $vendida;
            $item->cantidad_disponible = $disponible;
            $item->porcentaje_vendido = $cantidad > 0 ? round(($vendida / $cantidad) * 100, 2) : 0;
            return $item;
        });

        return response()->json($historial->values());
    }
    public function productosPorVencer(Request $request){
        $dias = (int) ($request->dias ?? 5);
        $perPage = (int) ($request->perPage ?? 10); // cantidad por página
        $page = (int) ($request->page ?? 1);        // página actual

        $hoy = Carbon::now();
        $limite = $hoy->copy()->addDays($dias);

        $productos = \App\Models\CompraDetalle::with('producto')
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$hoy->format('Y-m-d'), $limite->format('Y-m-d')])
            ->orderBy('fecha_vencimiento')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($productos);
    }

    public function productosVencidos(Request $request)
    {
        $hoy = Carbon::now()->format('Y-m-d');
        $perPage = $request->per_page ?? 10;

        $productos = \App\Models\CompraDetalle::with('producto')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->orderBy('fecha_vencimiento', 'desc')
            ->paginate($perPage);

        return response()->json($productos);
    }


    public function index(Request $request){
        $query = Compra::with(['user', 'proveedor', 'compraDetalles.producto'])->orderBy('id', 'desc');

        if ($request->fechaInicio && $request->fechaFin) {
            $query->whereBetween('fecha', [$request->fechaInicio, $request->fechaFin]);
        }

        if ($request->user) {
            $query->where('user_id', $request->user);
        }

        return $query->orderByDesc('fecha')->get();
    }

    public function anular($id)
    {
        DB::beginTransaction();
        try {
            $compra = Compra::with('compraDetalles')->findOrFail($id);

            if ($compra->estado === 'Anulado') {
                return response()->json(['message' => 'La compra ya fue anulada'], 400);
            }

            foreach ($compra->compraDetalles as $detalle) {
//                Producto::where('id', $detalle->producto_id)->decrement('stock', $detalle->cantidad);
//                switch ($compra->agencia) {
//                    case 'Almacen':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockAlmacen', $detalle->cantidad);
//                        break;
//                    case 'Challgua':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockChallgua', $detalle->cantidad);
//                        break;
//                    case 'Socavon':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockSocavon', $detalle->cantidad);
//                        break;
//                    case 'Catalina':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockCatalina', $detalle->cantidad);
//                        break;
//                }

                $detalle->estado = 'Anulado';
                $detalle->save();
            }

            $compra->estado = 'Anulado';
            $compra->save();

            DB::commit();
            return response()->json(['message' => 'Compra anulada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al anular la compra', 'error' => $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->validate([
                'tipo_pago' => ['required', Rule::in(['Efectivo', 'QR'])],
                'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
                'nro_factura' => ['nullable', 'string', 'max:255'],
                'agencia' => ['nullable', 'string', 'max:255'],
                'facturado' => ['nullable', 'boolean'],
                'foto' => ['nullable', 'image', 'max:5120'],
                'fecha_hora' => ['nullable', 'date'],
                'productos' => ['required', 'array', 'min:1'],
                'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
                'productos.*.cantidad' => ['required', 'numeric', 'min:0.001'],
                'productos.*.precio' => ['required', 'numeric', 'min:0'],
                'productos.*.factor' => ['nullable', 'numeric', 'min:0'],
                'productos.*.precio_venta' => ['required', 'numeric', 'min:0'],
                'productos.*.lote' => ['required', 'string', 'max:255'],
                'productos.*.fecha_vencimiento' => ['required', 'date'],
            ]);

            $fechaHora = isset($data['fecha_hora'])
                ? Carbon::parse($data['fecha_hora'])
                : Carbon::now();

            $fecha = $fechaHora->format('Y-m-d');
            $hora = $fechaHora->format('H:i:s');
            $proveedor = Proveedor::findOrFail($data['proveedor_id']);

            $fotoPath = null;
            if ($request->hasFile('foto')) {
                File::ensureDirectoryExists(public_path('uploads/compras'));
                $ext = strtolower($request->file('foto')->getClientOriginalExtension() ?: 'jpg');
                $name = uniqid('compra_', true) . '.' . $ext;
                $request->file('foto')->move(public_path('uploads/compras'), $name);
                $fotoPath = 'uploads/compras/' . $name;
            }

            $compra = Compra::create([
                'user_id' => auth()->id(),
                'proveedor_id' => $data['proveedor_id'],
                'fecha' => $fecha,
                'hora' => $hora,
                'fecha_hora' => $fechaHora,
                'ci' => $proveedor->ci ?? null,
                'nombre' => $proveedor->nombre ?? null,
                'estado' => 'Activo',
                'tipo_pago' => $data['tipo_pago'],
                'total' => collect($data['productos'])->sum(fn($p) => (float)$p['precio'] * (float)$p['cantidad']),
                'nro_factura' => $data['nro_factura'] ?? null,
                'agencia' => $data['agencia'] ?? null,
                'facturado' => (bool)($data['facturado'] ?? false),
                'foto' => $fotoPath,
            ]);

            foreach ($data['productos'] as $p) {
                $producto = Producto::findOrFail($p['producto_id']);
                $factor = (float)($p['factor'] ?? 1.25);
                $precio = (float)$p['precio'];
                $cantidad = (float)$p['cantidad'];

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'user_id' => auth()->id(),
                    'producto_id' => $p['producto_id'],
                    'proveedor_id' => $compra->proveedor_id,
                    'nombre' => $producto->nombre,
                    'precio' => $precio,
                    'cantidad' => $cantidad,
                    'cantidad_venta' => $cantidad,
                    'total' => $precio * $cantidad,
                    'factor' => $factor,
                    'precio13' => $precio * $factor,
                    'total13' => $precio * $cantidad * $factor,
                    'precio_venta' => $p['precio_venta'],
                    'estado' => 'Activo',
                    'lote' => trim((string)$p['lote']),
                    'fecha_vencimiento' => $p['fecha_vencimiento'],
                    'nro_factura' => $compra->nro_factura,
                ]);

                $producto->precio1 = $p['precio_venta'];
                $producto->save();
            }

            DB::commit();
            $compraSearch = Compra::with(['user', 'proveedor', 'compraDetalles.producto'])->find($compra->id);
            return $compraSearch;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar la compra', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateDatos(Request $request, Compra $compra)
    {
        $data = $request->validate([
            'nro_factura' => ['nullable', 'string', 'max:255'],
            'agencia' => ['nullable', 'string', 'max:255'],
            'facturado' => ['nullable', 'boolean'],
            'foto' => ['nullable', 'image', 'max:5120'],
            'remove_foto' => ['nullable', 'boolean'],
        ]);

        $payload = [];

        if ($request->has('nro_factura')) {
            $payload['nro_factura'] = $data['nro_factura'] ?? null;
        }

        if ($request->has('agencia')) {
            $payload['agencia'] = $data['agencia'] ?? null;
        }

        if ($request->has('facturado')) {
            $payload['facturado'] = (bool)($data['facturado'] ?? false);
        }

        if ($request->hasFile('foto')) {
            File::ensureDirectoryExists(public_path('uploads/compras'));
            $ext = strtolower($request->file('foto')->getClientOriginalExtension() ?: 'jpg');
            $name = uniqid('compra_', true) . '.' . $ext;
            $request->file('foto')->move(public_path('uploads/compras'), $name);
            $newPath = 'uploads/compras/' . $name;

            if (!empty($compra->foto) && str_starts_with($compra->foto, 'uploads/compras/')) {
                $old = public_path($compra->foto);
                if (File::exists($old)) {
                    File::delete($old);
                }
            }

            $payload['foto'] = $newPath;
        }

        if (($data['remove_foto'] ?? false) === true) {
            if (!empty($compra->foto) && str_starts_with($compra->foto, 'uploads/compras/')) {
                $old = public_path($compra->foto);
                if (File::exists($old)) {
                    File::delete($old);
                }
            }
            $payload['foto'] = null;
        }

        if (!empty($payload)) {
            $compra->update($payload);
        }

        return $compra->fresh(['user', 'proveedor', 'compraDetalles.producto']);
    }

}
