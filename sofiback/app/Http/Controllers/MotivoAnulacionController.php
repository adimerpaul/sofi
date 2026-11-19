<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Motivos de anulacion de facturas (tabla motivos_anulacion).
 *
 * Ver la lista de activos (para el dialogo de anular) basta con estar
 * logueado; administrarlos pide el permiso motivosAnulacion. En la factura se
 * guarda solo el texto, por eso editar o borrar no toca lo ya anulado.
 */
class MotivoAnulacionController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user() && $request->user()->can('motivosAnulacion'), 403,
                'No tiene permiso para administrar los motivos de anulación');
            return $next($request);
        })->except(['activos']);
    }

    /** Todos, para la pantalla de administracion. */
    public function index()
    {
        return DB::table('motivos_anulacion')->orderBy('orden')->orderBy('nombre')->get();
    }

    /** Solo los textos activos, para elegir al anular. */
    public function activos()
    {
        return DB::table('motivos_anulacion')->where('activo', true)
            ->orderBy('orden')->orderBy('nombre')->pluck('nombre');
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $id = DB::table('motivos_anulacion')->insertGetId($datos + [
            'orden' => (int) DB::table('motivos_anulacion')->max('orden') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('motivos_anulacion')->find($id);
    }

    public function update(Request $request, $id)
    {
        abort_unless(DB::table('motivos_anulacion')->where('id', $id)->exists(), 404, 'El motivo no existe');
        DB::table('motivos_anulacion')->where('id', $id)
            ->update($this->validar($request, $id) + ['updated_at' => now()]);

        return DB::table('motivos_anulacion')->find($id);
    }

    public function destroy($id)
    {
        abort_unless(DB::table('motivos_anulacion')->where('id', $id)->delete(), 404, 'El motivo no existe');

        return ['ok' => true];
    }

    private function validar(Request $request, $id = null)
    {
        // Se guarda en mayusculas y sin espacios de sobra, como los de siempre.
        $request->merge(['nombre' => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $request->nombre)))]);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('motivos_anulacion', 'nombre')->ignore($id)],
            'activo' => 'required|boolean',
        ], [
            'nombre.unique' => 'Ya existe un motivo con ese texto',
            'nombre.required' => 'Escriba el motivo',
        ]);

        return ['nombre' => $datos['nombre'], 'activo' => (bool) $datos['activo']];
    }
}
