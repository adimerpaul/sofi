<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Encuesta;

class EncuestaController extends Controller
{
    /**
     * Guardar una encuesta
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'score'   => 'required|in:0,5,10',
            'comment' => 'nullable|string|max:500',
            'ref_id'  => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:255',
        ]);
        error_log("Encuesta recibida: " . json_encode($validated));

        $encuesta = Encuesta::create([
            'score'   => $validated['score'],
            'comment' => $validated['comment'] ?? null,
            'ref_id'  => $validated['ref_id'] ?? null,
            'ip'      => $request->ip(),
            'ua'      => $request->userAgent(),
            'email'   => $validated['email'] ?? null,
        ]);

        return response()->json([
            'message'  => 'Encuesta registrada con éxito',
            'data'     => $encuesta
        ], 201);
    }

    /**
     * (Opcional) Listar encuestas
     */
    public function index()
    {
        return Encuesta::latest()->paginate(20);
    }
}
