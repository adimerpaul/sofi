<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Administracion de proveedores (tbproveedor, tabla del sistema legado).
 *
 * Ojo con dos cosas de esa tabla: todas sus columnas son NOT NULL y sin
 * default, y el NIT tiene indice unico. Un campo vacio del formulario llega
 * aqui como null (Laravel aplica ConvertEmptyStringsToNull) y hay que
 * guardarlo como cadena vacia o el INSERT revienta.
 */
class ProveedorController extends Controller
{
    /** Campos editables; el resto de la tabla no se toca desde la web. */
    private const CAMPOS = ['NIT', 'PROVEEDOR', 'DIRECCION', 'TELF'];

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('perPage', 15), 1), 200);

        $query = DB::table('tbproveedor')
            ->select([
                'CodAut as id',
                DB::raw('TRIM(NIT) as nit'),
                DB::raw('TRIM(PROVEEDOR) as proveedor'),
                DB::raw('TRIM(DIRECCION) as direccion'),
                DB::raw('TRIM(TELF) as telefono'),
                // Cuantas compras tiene: sirve para saber si se puede borrar.
                DB::raw('(SELECT COUNT(*) FROM compras c WHERE c.proveedor_id = tbproveedor.CodAut AND c.deleted_at IS NULL) as compras'),
            ]);

        if ($buscar = trim((string) $request->input('buscar', ''))) {
            $like = '%' . $buscar . '%';
            $query->where(function ($w) use ($like) {
                $w->where('PROVEEDOR', 'like', $like)
                    ->orWhere('NIT', 'like', $like)
                    ->orWhere('DIRECCION', 'like', $like);
            });
        }

        return $query->orderBy('PROVEEDOR')->paginate($perPage);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $nit = trim((string) ($datos['NIT'] ?? ''));

        if ($nit !== '' && $this->nitRepetido($nit)) {
            return response()->json(['message' => 'Ya existe un proveedor con el NIT ' . $nit], 422);
        }

        $proveedor = Proveedor::create($this->normalizar($datos));

        return response()->json([
            'message'    => 'Proveedor creado',
            'proveedor'  => $proveedor,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $datos = $this->validar($request);

        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'El proveedor no existe'], 404);
        }

        $nit = trim((string) ($datos['NIT'] ?? ''));
        if ($nit !== '' && $this->nitRepetido($nit, $id)) {
            return response()->json(['message' => 'Ya existe otro proveedor con el NIT ' . $nit], 422);
        }

        foreach ($this->normalizar($datos) as $campo => $valor) {
            $proveedor->$campo = $valor;
        }
        $proveedor->save();

        return response()->json([
            'message'   => 'Proveedor actualizado',
            'proveedor' => $proveedor->fresh(),
        ]);
    }

    /**
     * Borra el proveedor, salvo que tenga compras: la compra guarda su id y
     * borrarlo dejaria el historial apuntando a un proveedor inexistente.
     */
    public function destroy($id)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) {
            return response()->json(['message' => 'El proveedor no existe'], 404);
        }

        $compras = DB::table('compras')->where('proveedor_id', $id)->whereNull('deleted_at')->count();
        if ($compras > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: tiene ' . $compras . ' compra(s) registradas',
            ], 422);
        }

        $proveedor->delete();

        return response()->json(['message' => 'Proveedor eliminado']);
    }

    private function validar(Request $request)
    {
        // Lo obligatorio es el nombre: muchos proveedores chicos no dan NIT.
        // Los max coinciden con el ancho real de cada columna.
        // Los mensajes van a mano porque los nombres de columna del legado van
        // en mayusculas y Laravel los muestra letra por letra ("p r o v...").
        return $request->validate([
            'NIT'       => 'nullable|string|max:16',
            'PROVEEDOR' => 'required|string|max:60',
            'DIRECCION' => 'nullable|string|max:100',
            'TELF'      => 'nullable|string|max:50',
        ], [
            'PROVEEDOR.required' => 'El nombre del proveedor es obligatorio',
            'PROVEEDOR.max'      => 'El nombre no puede pasar de 60 caracteres',
            'NIT.max'            => 'El NIT no puede pasar de 16 caracteres',
            'DIRECCION.max'      => 'La dirección no puede pasar de 100 caracteres',
            'TELF.max'           => 'El teléfono no puede pasar de 50 caracteres',
        ]);
    }

    /**
     * El resto de columnas son NOT NULL, asi que lo vacio va como cadena.
     * El NIT es la excepcion: tiene indice unico y solo admite un '' en toda
     * la tabla, asi que sin NIT se guarda NULL, que el indice si permite
     * repetido.
     */
    private function normalizar(array $datos)
    {
        $limpio = [];
        foreach (self::CAMPOS as $campo) {
            $valor = $datos[$campo] ?? null;
            $valor = $valor === null ? '' : trim((string) $valor);

            $limpio[$campo] = ($campo === 'NIT' && $valor === '') ? null : $valor;
        }

        return $limpio;
    }

    private function nitRepetido($nit, $exceptoId = null)
    {
        $query = DB::table('tbproveedor')->whereRaw('TRIM(NIT) = ?', [trim($nit)]);

        if ($exceptoId !== null) {
            $query->where('CodAut', '<>', $exceptoId);
        }

        return $query->exists();
    }
}
