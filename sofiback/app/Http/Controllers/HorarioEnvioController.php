<?php

namespace App\Http\Controllers;

use App\Models\HorarioEnvio;
use Illuminate\Http\Request;

class HorarioEnvioController extends Controller
{
    public function index()
    {
        return HorarioEnvio::orderBy('hora')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'hora' => 'required|date_format:H:i',
            'dias' => 'required|string',
        ]);

        return HorarioEnvio::create([
            'hora' => $request->hora . ':00',
            'dias' => $request->dias,
            'activo' => $request->activo ?? 1,
        ]);
    }

    public function update(Request $request, $id)
    {
        $horario = HorarioEnvio::findOrFail($id);

        $data = $request->only(['dias', 'activo']);
        if ($request->filled('hora')) {
            $request->validate(['hora' => 'date_format:H:i']);
            $data['hora'] = $request->hora . ':00';
            // si cambia la hora se permite que vuelva a ejecutarse hoy
            $data['ultima_ejecucion'] = null;
        }
        $horario->update($data);

        return $horario;
    }

    public function destroy($id)
    {
        HorarioEnvio::findOrFail($id)->delete();
        return response()->json(['message' => 'Horario eliminado']);
    }
}
