<?php

// app/Http/Controllers/EncuestaController.php
namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Encuesta;
use Carbon\Carbon;

class EncuestaController extends Controller{
    public function reportPdf(Request $request)
    {
        $request->validate([
            'from'    => 'nullable|date',
            'to'      => 'nullable|date',
            'usuario' => 'nullable|string',
            'score'   => 'nullable|in:0,5,10',
        ]);

        $q = Encuesta::query();

        // Fechas (encuesta_date en tu modelo)
        if ($from = $request->input('from')) {
            $q->whereDate('encuesta_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $q->whereDate('encuesta_date', '<=', $to);
        }

        // Score exacto
        if ($request->filled('score')) {
            $q->where('score', (int) $request->score);
        }

        // Usuario: si es número => usuario_cod_aut; si es texto => nombre/ci/correo
        if ($request->filled('usuario')) {
            $u = trim($request->usuario);
            if (is_numeric($u)) {
                $q->where('usuario_cod_aut', (int)$u);
            } else {
                $q->where(function ($qq) use ($u) {
                    $like = '%' . str_replace('%', '\%', $u) . '%';
                    $qq->where('usuario_nombre', 'like', $like)
                        ->orWhere('usuario_ci', 'like', $like)
                        ->orWhere('usuario_correo', 'like', $like);
                });
            }
        }

        $rows = $q->orderByDesc('created_at')->get();

        // Totales para cabecera
        $total = $rows->count();
        $t10   = $rows->where('score', 10)->count();
        $t5    = $rows->where('score', 5)->count();
        $t0    = $rows->where('score', 0)->count();

        // Metadatos de filtros para mostrar en el PDF
        $filters = [
            'from'    => $request->input('from'),
            'to'      => $request->input('to'),
            'usuario' => $request->input('usuario'),
            'score'   => $request->input('score'),
        ];

        // Opciones útiles (evitar warnings por HTML5, etc.)
        $pdf = Pdf::loadView('pdf.report', [
            'rows'    => $rows,
            'filters' => $filters,
            'total'   => $total,
            't10'     => $t10,
            't5'      => $t5,
            't0'      => $t0,
            'now'     => now('America/La_Paz'),
        ])->setPaper('a4', 'portrait');

        $filename = 'reporte-encuestas-' . now('America/La_Paz')->format('Ymd_His') . '.pdf';

        // stream() para abrir en el navegador; download() si prefieres descarga directa
        return $pdf->stream($filename);
    }
    /**
     * POST /api/encuestas
     * Body esperado:
     * {
     *   idcliente: number,   // tbclientes.Cod_Aut
     *   iduser: number,      // personal.CodAut
     *   score: 0|5|10,
     *   comment?: string,
     *   email?: string        // email de Google de quien responde
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'idcliente' => 'required|integer|min:1',
            'iduser'    => 'required|integer|min:1',
            'score'     => 'required|in:0,5,10',
            'comment'   => 'nullable|string|max:500',
            'email'     => 'nullable|email|max:255',
        ]);

        $clienteId = (int)$validated['idcliente'];
        $userId    = (int)$validated['iduser'];

        // Cargar snapshot de cliente (BD: sofia.tbclientes)
        $cli = DB::table('tbclientes')
            ->where('Cod_Aut', $clienteId)
            ->first();

        if (!$cli) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        // Cargar snapshot de usuario (BD: sofia.personal)
        $usr = DB::table('personal')
            ->where('CodAut', $userId)
            ->first();

        if (!$usr) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Anti-fraude: si el email que responde == correo del repartidor -> 403
        if (!empty($validated['email']) && !empty($usr->correo)) {
            if (strcasecmp(trim($validated['email']), trim($usr->correo)) === 0) {
                return response()->json([
                    'message' => 'Esta encuesta es únicamente para el cliente. El correo del repartidor no puede responder.'
                ], 403);
            }
        }

        // Evitar duplicados (por día lógico)
        $hoy = Carbon::now('America/La_Paz')->toDateString();

        $exists = Encuesta::where('cliente_cod_aut', $clienteId)
            ->where('usuario_cod_aut', $userId)
            ->where('encuesta_date', $hoy)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe una respuesta para este cliente y usuario hoy.'
            ], 409);
        }

        // Metadatos request
        $scheme = $request->getScheme();
        $host   = $request->getHost();
        $path   = $request->path();
        $serverIp = $request->server('SERVER_ADDR');
        $referer  = $request->headers->get('referer');

        // Build nombre completo del usuario
        $usuarioNombre = trim(
            implode(' ', array_filter([
                $usr->Nombre1 ?? null,
                $usr->Nombre2 ?? null,
                $usr->App1 ?? null,
                $usr->Apm ?? null,
            ]))
        );

        $encuesta = Encuesta::create([
            'cliente_cod_aut' => $clienteId,
            'usuario_cod_aut' => $userId,

            'cliente_id'      => $cli->Id ?? null,
            'cliente_nombre'  => $cli->Nombres ?? null,
            'cliente_tel'     => $cli->Telf ?? null,
            'cliente_dir'     => $cli->Direccion ?? null,
            'cliente_zona'    => $cli->zona ?? null,
            'cliente_lat'     => $cli->Latitud ?? null,
            'cliente_lng'     => $cli->longitud ?? null,

            'usuario_ci'      => $usr->ci ?? null,
            'usuario_nombre'  => $usuarioNombre ?: null,
            'usuario_correo'  => $usr->correo ?? null,
            'usuario_placa'   => $usr->placa ?? null,

            'score'           => (int)$validated['score'],
            'comment'         => $validated['comment'] ?? null,

            'encuesta_date'   => $hoy,
            'email'           => $validated['email'] ?? null,

            'client_ip'       => $request->ip(),
            'origin_scheme'   => $scheme,
            'origin_host'     => $host,
            'origin_path'     => $path,
            'server_ip'       => $serverIp ?: null,
            'user_agent'      => $request->userAgent(),
            'referer'         => $referer ?: null,
        ]);

        return response()->json([
            'message' => 'Encuesta registrada con éxito',
            'data'    => $encuesta
        ], 201);
    }

    /**
     * GET /api/encuestas/check?idcliente=..&iduser=..
     * Devuelve si YA existe una respuesta hoy (para deshabilitar el front).
     */
    public function check(Request $request)
    {
        $request->validate([
            'idcliente' => 'required|integer|min:1',
            'iduser'    => 'required|integer|min:1',
        ]);
        $hoy = now('America/La_Paz')->toDateString();

        $exists = Encuesta::where('cliente_cod_aut', (int)$request->idcliente)
            ->where('usuario_cod_aut', (int)$request->iduser)
            ->where('encuesta_date', $hoy)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * (Opcional) Listado con filtros por fecha
     */
    public function index(Request $request)
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $q = Encuesta::query();
        if ($from = $request->input('from')) $q->whereDate('encuesta_date', '>=', $from);
        if ($to   = $request->input('to'))   $q->whereDate('encuesta_date', '<=', $to);
        $q->orderByDesc('created_at');

        $perPage = (int)($request->input('per_page', 25));
        $perPage = max(1, min(200, $perPage));

        return response()->json($q->paginate($perPage));
    }
}
