<?php

namespace App\Http\Controllers;

use App\Models\PedidoZona;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PedidoZonaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeZoneAccess($request);

        return PedidoZona::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    public function store(Request $request)
    {
        $this->authorizeZoneAccess($request);

        $data = $request->validate([
            'nombre' => 'required|string|max:80|unique:pedido_zonas,nombre',
            'color' => 'required|string|max:20',
            'orden' => 'nullable|integer|min:0|max:99999',
            'activo' => 'nullable|boolean',
        ]);

        return PedidoZona::create([
            'nombre' => trim($data['nombre']),
            'color' => trim($data['color']),
            'orden' => (int) ($data['orden'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? true),
        ]);
    }

    public function update(Request $request, PedidoZona $pedidoZona)
    {
        $this->authorizeZoneAccess($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('pedido_zonas', 'nombre')->ignore($pedidoZona->id)],
            'color' => 'required|string|max:20',
            'orden' => 'nullable|integer|min:0|max:99999',
            'activo' => 'nullable|boolean',
        ]);

        $pedidoZona->update([
            'nombre' => trim($data['nombre']),
            'color' => trim($data['color']),
            'orden' => (int) ($data['orden'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return $pedidoZona->fresh();
    }

    public function destroy(Request $request, PedidoZona $pedidoZona)
    {
        $this->authorizeZoneAccess($request);

        $pedidoZona->delete();
        return response()->json(['message' => 'Zona eliminada']);
    }

    private function authorizeZoneAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 401, 'No autenticado');

        $isAdmin = strtoupper((string) ($user->role ?? '')) === 'ADMIN';
        $canZonas = method_exists($user, 'can') && $user->can('Mapa cliente zonas');
        abort_unless($isAdmin || $canZonas, 403, 'No autorizado');
    }
}

