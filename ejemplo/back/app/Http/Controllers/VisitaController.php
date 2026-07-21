<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Visita;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitaController extends Controller
{
    public function clientes(Request $request)
    {
        $forcedClienteIds = [1520, 1179];
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 500);
        $soloMios = (bool) $request->input('solo_mios', true);
        $soloDia = (bool) $request->input('solo_dia', true);
        $ciVend = trim((string) $request->input('ci_vend', ''));
        $zona = trim((string) $request->input('zona', ''));
        $ventaEstado = trim((string) $request->input('venta_estado', ''));
        $dayName = strtolower((string) $request->input('dia', Carbon::now()->locale('es')->isoFormat('ddd')));
        $dayMap = [
            'lu' => 'lu', 'lun' => 'lu', 'lunes' => 'lu',
            'ma' => 'ma', 'mar' => 'ma', 'martes' => 'ma',
            'mi' => 'mi', 'mie' => 'mi', 'miercoles' => 'mi', 'miércoles' => 'mi',
            'ju' => 'ju', 'jue' => 'ju', 'jueves' => 'ju',
            'vi' => 'vi', 'vie' => 'vi', 'viernes' => 'vi',
            'sa' => 'sa', 'sab' => 'sa', 'sabado' => 'sa', 'sábado' => 'sa',
            'do' => 'do', 'dom' => 'do', 'domingo' => 'do',
        ];
        $dayField = $dayMap[$dayName] ?? null;
        $user = $request->user();
        $query = Cliente::query()->with(['vendedorUser:id,name,username,avatar']);

        $applyBaseFilters = function ($q) use ($soloMios, $user, $soloDia, $dayField, $ciVend, $zona, $ventaEstado, $search) {
            $q->when($soloMios && $user, function ($subQuery) use ($user) {
                $subQuery->where('ci_vend', $user->username);
            })
                ->when($soloDia && $dayField, function ($subQuery) use ($dayField) {
                    $subQuery->where($dayField, true);
                })
                ->when($ciVend !== '', function ($subQuery) use ($ciVend) {
                    $subQuery->where('ci_vend', $ciVend);
                })
                ->when($zona !== '', function ($subQuery) use ($zona) {
                    $subQuery->where('zona', $zona);
                })
                ->when($ventaEstado !== '', function ($subQuery) use ($ventaEstado) {
                    $subQuery->where('venta_estado', $ventaEstado);
                })
                ->when($search !== '', function ($subQuery) use ($search) {
                    $subQuery->where(function ($searchQuery) use ($search) {
                        $searchQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('nit', 'like', "%{$search}%")
                            ->orWhere('ci', 'like', "%{$search}%")
                            ->orWhere('telefono', 'like', "%{$search}%")
                            ->orWhere('codcli', 'like', "%{$search}%");
                    });
                });
        };

        $query->where(function ($q) use ($applyBaseFilters, $forcedClienteIds) {
            $q->where(function ($baseQuery) use ($applyBaseFilters) {
                $applyBaseFilters($baseQuery);
            })->orWhereIn('id', $forcedClienteIds);
        });

        return $query
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $soloMios = (bool) $request->input('solo_mios', true);
        $allDays = (bool) $request->input('all_days', true);
        $latestPerCliente = (bool) $request->input('latest_per_cliente', true);
        $fecha = $request->input('fecha', now()->toDateString());

        $query = Visita::query()
            ->with(['cliente:id,nombre,codcli,direccion,telefono,latitud,longitud'])
            ->when($soloMios && $user, function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->when(!$allDays, function ($q) use ($fecha) {
                $q->whereDate('fecha', $fecha);
            })
            ->when($request->filled('cliente_id'), function ($q) use ($request) {
                $q->where('cliente_id', (int) $request->input('cliente_id'));
            })
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->orderByDesc('id');

        if ($request->boolean('paginate', false) && !$latestPerCliente) {
            return response()->json($query->paginate((int) $request->input('per_page', 100)));
        }

        $items = $query->get();

        if ($latestPerCliente) {
            $items = $items->unique('cliente_id')->values();
        }

        return response()->json($items);
    }
}
