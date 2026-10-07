<?php

namespace App\Http\Controllers;

use App\Models\ClientePrecio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pestaña Precios de Clientes: que precio (1 a 13) se le cobra al cliente en
 * cada grupo de productos.
 */
class ClientePrecioController extends Controller
{
    /** Los grupos del cliente con su precio y quien lo creo o cambio. */
    public function index(Request $request, $cliente)
    {
        abort_unless(DB::table('tbclientes')->where('Cod_Aut', $cliente)->exists(), 404, 'Cliente no encontrado');
        // Un cliente creado por otro lado, o un grupo nuevo, completan aca.
        ClientePrecio::completarCliente($cliente, optional($request->user())->CodAut);

        $nombre = function ($tabla, $como) {
            return DB::raw("UPPER(TRIM(CONCAT(TRIM(COALESCE({$tabla}.Nombre1, '')), ' ', TRIM(COALESCE({$tabla}.App1, ''))))) as {$como}");
        };

        return DB::table('cliente_precios as cp')
            ->join('tbgrupos as g', DB::raw('TRIM(g.Cod_grup)'), '=', 'cp.cod_grup')
            ->leftJoin('tbgrupopadre as gp', DB::raw('TRIM(gp.cod_grup)'), '=', DB::raw('TRIM(g.Cod_pdr)'))
            ->leftJoin('personal as c', 'c.CodAut', '=', 'cp.created_by')
            ->leftJoin('personal as u', 'u.CodAut', '=', 'cp.updated_by')
            ->where('cp.cliente_id', $cliente)
            ->orderByRaw('TRIM(gp.Descripcion)')->orderByRaw('TRIM(g.Descripcion)')
            ->get([
                'cp.id', 'cp.cod_grup', 'cp.precio', 'cp.created_at', 'cp.updated_at',
                DB::raw('TRIM(g.Descripcion) as grupo'),
                DB::raw("TRIM(COALESCE(gp.Descripcion, '')) as padre"),
                'cp.created_by', $nombre('c', 'creado_por'),
                'cp.updated_by', $nombre('u', 'modificado_por'),
            ])
            ->map(function ($p) {
                return [
                    'id' => (int) $p->id,
                    'cod_grup' => $p->cod_grup,
                    'grupo' => $p->grupo,
                    'padre' => $p->padre,
                    'precio' => (int) $p->precio,
                    'creado_por' => $p->created_by ? ($p->creado_por ?: 'Usuario #' . $p->created_by) : 'SISTEMA',
                    'creado_en' => substr((string) $p->created_at, 0, 16),
                    'modificado_por' => $p->updated_by ? ($p->modificado_por ?: 'Usuario #' . $p->updated_by) : 'SISTEMA',
                    'modificado_en' => substr((string) $p->updated_at, 0, 16),
                ];
            });
    }

    /**
     * Cambia el precio de uno o varios grupos del cliente:
     * precios = [{cod_grup, precio}]. Solo se toca lo que cambia.
     */
    public function guardar(Request $request, $cliente)
    {
        $datos = $request->validate([
            'precios' => 'required|array|min:1',
            'precios.*.cod_grup' => 'required|string|max:10',
            'precios.*.precio' => 'required|integer|min:1|max:13',
        ]);
        abort_unless(DB::table('tbclientes')->where('Cod_Aut', $cliente)->exists(), 404, 'Cliente no encontrado');
        $usuario = optional($request->user())->CodAut;
        ClientePrecio::completarCliente($cliente, $usuario);

        $cambiados = 0;
        DB::transaction(function () use ($datos, $cliente, $usuario, &$cambiados) {
            foreach ($datos['precios'] as $p) {
                $cambiados += ClientePrecio::where('cliente_id', $cliente)
                    ->where('cod_grup', trim($p['cod_grup']))
                    ->where('precio', '<>', (int) $p['precio'])
                    ->update(['precio' => (int) $p['precio'], 'updated_by' => $usuario, 'updated_at' => date('Y-m-d H:i:s')]);
            }
        });

        return [
            'message' => $cambiados ? 'Precios guardados (' . $cambiados . ')' : 'Sin cambios',
            'precios' => $this->index($request, $cliente),
        ];
    }
}
