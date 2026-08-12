<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\ProductoGrupo;
use App\Models\ProductoGrupoPadre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductoController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:productos,id',
            'image' => 'required|image|max:5120',
        ]);

        $producto = Producto::findOrFail($request->id);

        $manager = new ImageManager(new Driver());
        $imageFile = $request->file('image');
        $image = $manager->read($imageFile->getPathname());

        if ($image->width() > 800) {
            $image = $image->scale(width: 800);
        }

        $extension = strtolower($imageFile->getClientOriginalExtension());
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $encoded = $image->toJpeg(quality: 80);
                $extension = 'jpg';
                break;
            case 'png':
                $encoded = $image->toPng();
                $extension = 'png';
                break;
            case 'webp':
                $encoded = $image->toWebp(quality: 80);
                $extension = 'webp';
                break;
            default:
                $encoded = $image->toJpeg(quality: 80);
                $extension = 'jpg';
                break;
        }

        $filename = 'uploads/' . uniqid('prod_', true) . '.' . $extension;
        $destination = public_path($filename);

        File::ensureDirectoryExists(dirname($destination));
        file_put_contents($destination, (string) $encoded);

        if (!empty($producto->imagen) && str_starts_with($producto->imagen, 'uploads/')) {
            $old = public_path($producto->imagen);
            if (File::exists($old) && !str_ends_with($producto->imagen, 'default.png')) {
                File::delete($old);
            }
        }

        $producto->imagen = $filename;
        $producto->save();

        return response()->json($producto->fresh(), 200);
    }

    public function gruposPadres()
    {
        return ProductoGrupoPadre::orderBy('nombre')->get(['id', 'nombre', 'codigo']);
    }

    public function grupos(Request $request)
    {
        return ProductoGrupo::query()
            ->when($request->filled('producto_grupo_padre_id'), function ($q) use ($request) {
                $q->where('producto_grupo_padre_id', $request->producto_grupo_padre_id);
            })
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'producto_grupo_padre_id']);
    }

    public function historialComprasVentas($productoId)
    {
        $detalles = \App\Models\CompraDetalle::with(['compra' => function ($q) {
            $q->select('id', 'agencia');
        }])
            ->where('producto_id', $productoId)
            ->where('estado', 'Activo')
            ->whereNull('deleted_at')
            ->where('cantidad_venta', '>', 0)
            ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
            ->get(['id', 'compra_id', 'producto_id', 'lote', 'fecha_vencimiento', 'cantidad', 'cantidad_venta', 'precio', 'factor', 'precio_venta', 'nro_factura']);

        $response = $detalles->map(function ($d) {
            return [
                'id' => $d->id,
                'compra_id' => $d->compra_id,
                'agencia' => $d->compra?->agencia,
                'producto_id' => $d->producto_id,
                'lote' => $d->lote,
                'fecha_vencimiento' => $d->fecha_vencimiento,
                'cantidad' => (float) $d->cantidad,
                'disponible' => (float) $d->cantidad_venta,
                'precio' => (float) $d->precio,
                'factor' => (float) $d->factor,
                'precio_venta' => (float) $d->precio_venta,
                'nro_factura' => $d->nro_factura,
            ];
        });

        return response()->json($response);
    }

    public function productosStock(Request $request)
    {
        $search = trim($request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);

        $productos = Producto::query()
            ->withSum([
                'comprasDetalles as stock' => function ($q) {
                    $q->where('estado', 'Activo');
                }
            ], 'cantidad_venta')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('productos.nombre', 'like', "%{$search}%")
                        ->orWhere('productos.codigo', 'like', "%{$search}%");
                });
            })
            ->having('stock', '>', 0)
            ->orderBy('productos.nombre')
            ->paginate($perPage);

        return response()->json($productos);
    }

    public function productosAll()
    {
        return Producto::with(['productoGrupo:id,nombre,codigo', 'productoGrupoPadre:id,nombre,codigo'])
            ->orderBy('nombre')
            ->get();
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $agencia = $request->agencia;
        $active = $request->input('active');
        $tipo = strtoupper(trim((string) $request->input('tipo', '')));
        $codigoUnidad = strtoupper(trim((string) $request->input('codigo_unidad', '')));
        $tiposValidos = ['NORMAL', 'POLLO', 'RES', 'CERDO'];
        $unidadesValidas = ['KG', 'U', 'UNIDA'];

        $productos = Producto::query()
            ->with(['productoGrupo:id,nombre,codigo,producto_grupo_padre_id', 'productoGrupoPadre:id,nombre,codigo'])
            ->when($active !== null && $active !== '', function ($q) use ($active) {
                $q->where('active', (int)$active);
            })
            ->when(in_array($tipo, $tiposValidos, true), function ($q) use ($tipo) {
                $q->where('tipo', $tipo);
            })
            ->when(in_array($codigoUnidad, $unidadesValidas, true), function ($q) use ($codigoUnidad) {
                $q->where('codigo_unidad', $codigoUnidad);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%");
                });
            })
            ->withSum([
                'comprasDetalles as stock_disponible' => function ($q) use ($agencia) {
                    $q->where('estado', 'Activo')
                        ->whereNull('deleted_at');
                    if (!empty($agencia)) {
                        $q->whereHas('compra', function ($qc) use ($agencia) {
                            $qc->where('agencia', $agencia);
                        });
                    }
                }
            ], 'cantidad_venta')
            ->orderBy('nombre')
            ->paginate($perPage);

        return response()->json($productos);
    }

    public function store(Request $request)
    {
        $data = $this->validateProductData($request, null);
        $payload = $this->preparePayload($data, true);

        $producto = Producto::create($payload);

        return response()->json($producto, 201);
    }

    public function update(Request $request, Producto $producto)
    {
        $data = $this->validateProductData($request, $producto);
        $payload = $this->preparePayload($data, false, $producto);

        $producto->update($payload);

        return response()->json($producto->fresh());
    }

    public function destroy(Producto $producto)
    {
        $producto->delete();

        return response()->json(['success' => true]);
    }

    private function validateProductData(Request $request, ?Producto $producto): array
    {
        $isUpdate = $producto !== null;

        $rules = [
            'codigo' => [
                $isUpdate ? 'sometimes' : 'nullable',
                'string',
                'max:25',
                Rule::unique('productos', 'codigo')->ignore($producto?->id),
            ],
            'imagen' => [$isUpdate ? 'sometimes' : 'nullable', 'string', 'max:255'],
            'producto_grupo_id' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'exists:producto_grupos,id'],
            'producto_grupo_padre_id' => [$isUpdate ? 'sometimes' : 'nullable', 'integer', 'exists:producto_grupo_padres,id'],
            'nombre' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:105'],
            'tipo_producto' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:3'],
            'codigo_unidad' => [$isUpdate ? 'sometimes' : 'nullable', 'string', 'max:15'],
            'unidades_caja' => [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'cantidad_presentacion' => [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'peso_estimado' => [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'tipo' => [$isUpdate ? 'sometimes' : 'nullable'],
            'oferta' => [$isUpdate ? 'sometimes' : 'nullable'],
            'codigo_producto_sin' => [$isUpdate ? 'sometimes' : 'nullable', 'string', 'max:100'],
            'presentacion' => [$isUpdate ? 'sometimes' : 'nullable'],
            'codigo_grupo_sin' => [$isUpdate ? 'sometimes' : 'nullable', 'string', 'max:100'],
            'credito' => [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
            'active' => [$isUpdate ? 'sometimes' : 'nullable', 'boolean'],
            // Legacy aliases still accepted.
            'barra' => [$isUpdate ? 'sometimes' : 'nullable', 'string', 'max:25'],
            'precio' => [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'],
        ];

        for ($i = 1; $i <= 13; $i++) {
            $rules['precio' . $i] = [$isUpdate ? 'sometimes' : 'nullable', 'numeric', 'min:0'];
        }

        return $request->validate($rules);
    }

    private function preparePayload(array $data, bool $isCreate, ?Producto $producto = null): array
    {
        if (isset($data['barra']) && !isset($data['codigo'])) {
            $data['codigo'] = $data['barra'];
        }

        if (isset($data['precio']) && !isset($data['precio1'])) {
            $data['precio1'] = $data['precio'];
        }

        unset($data['barra'], $data['precio']);

        if (($isCreate || array_key_exists('codigo', $data)) && empty($data['codigo'])) {
            $data['codigo'] = $this->generateCodigo();
        }

        if ($isCreate && !isset($data['active'])) {
            $data['active'] = true;
        }

        if (isset($data['tipo'])) {
            $data['tipo'] = strtoupper(trim((string) $data['tipo']));
        } elseif ($isCreate) {
            $data['tipo'] = 'NORMAL';
        }

        if ($isCreate && !isset($data['oferta'])) {
            $data['oferta'] = ' ';
        }

        if (isset($data['producto_grupo_id']) && !isset($data['producto_grupo_padre_id'])) {
            $grupo = ProductoGrupo::select('id', 'producto_grupo_padre_id')->find($data['producto_grupo_id']);
            if ($grupo) {
                $data['producto_grupo_padre_id'] = $grupo->producto_grupo_padre_id;
            }
        }

        // In create, fill missing prices from precio1.
        if ($isCreate) {
            $base = isset($data['precio1']) ? (float) $data['precio1'] : 0;
            for ($i = 1; $i <= 13; $i++) {
                $k = 'precio' . $i;
                if (!isset($data[$k])) {
                    $data[$k] = $base;
                }
            }
        }

        return $data;
    }

    private function generateCodigo(): string
    {
        $next = ((int) Producto::max('id')) + 1;
        $codigo = 'PROD-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        while (Producto::where('codigo', $codigo)->exists()) {
            $next++;
            $codigo = 'PROD-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }
}
