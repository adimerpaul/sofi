<?php

namespace App\Http\Controllers;

use App\Models\TipoProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tipos de producto: se administran desde Productos y se ven en el pedido
 * de Visita (chips para filtrar y la marca de cada linea).
 */
class TipoProductoController extends Controller
{
    public function index()
    {
        return TipoProducto::query()
            ->select('tipos_producto.*')
            ->selectSub(function ($q) {
                $q->from('tbproductos')->selectRaw('COUNT(*)')
                    ->whereColumn('tbproductos.tipo_producto_id', 'tipos_producto.id')
                    ->whereNull('tbproductos.deleted_at');
            }, 'productos')
            ->orderBy('orden')->orderBy('nombre')
            ->get();
    }

    public function store(Request $request)
    {
        $tipo = TipoProducto::create($this->validar($request) + [
            'orden' => (int) TipoProducto::max('orden') + 1,
        ]);

        return response()->json(['message' => 'Tipo ' . $tipo->nombre . ' creado', 'tipo' => $tipo], 201);
    }

    /**
     * Cambiar el modo de un tipo cambia el formulario de todos sus productos,
     * asi que tbproductos.tipo se actualiza junto.
     */
    public function update(Request $request, $id)
    {
        $tipo = TipoProducto::findOrFail($id);
        $datos = $this->validar($request, $tipo->id);

        DB::transaction(function () use ($tipo, $datos) {
            $tipo->update($datos);
            DB::table('tbproductos')->where('tipo_producto_id', $tipo->id)->update(['tipo' => $tipo->modo]);
        });

        return response()->json(['message' => 'Tipo ' . $tipo->nombre . ' actualizado', 'tipo' => $tipo]);
    }

    /** Solo se borra un tipo que ningun producto usa (ni los eliminados). */
    public function destroy($id)
    {
        $tipo = TipoProducto::findOrFail($id);
        $usados = DB::table('tbproductos')->where('tipo_producto_id', $tipo->id)->count();
        if ($usados > 0) {
            return response()->json([
                'message' => 'El tipo ' . $tipo->nombre . ' lo usan ' . $usados . ' productos: cámbielos de tipo antes de borrarlo',
            ], 422);
        }
        $tipo->delete();

        return response()->json(['message' => 'Tipo ' . $tipo->nombre . ' eliminado']);
    }

    private function validar(Request $request, $ignorar = null)
    {
        $request->merge(['nombre' => mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $request->input('nombre'))))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:30', Rule::unique('tipos_producto', 'nombre')->ignore($ignorar)],
            'modo'   => 'required|in:' . implode(',', TipoProducto::MODOS),
            'color'  => 'nullable|string|max:20',
        ], [
            'nombre.unique' => 'Ya existe un tipo con ese nombre',
        ]);
        $datos['color'] = ($datos['color'] ?? '') ?: 'primary';

        return $datos;
    }
}
