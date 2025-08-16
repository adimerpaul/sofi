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
    public function index(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'all'      => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $q = Encuesta::query();

        if ($from = $request->input('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        $q->orderByDesc('created_at');

        // Si piden todo, devolvemos una colección sin paginar
        if ($request->boolean('all')) {
            return response()->json($q->get());
        }

        $perPage = (int)($request->input('per_page', 25));
        $perPage = max(1, min(200, $perPage));

        return response()->json($q->paginate($perPage));
    }

}
