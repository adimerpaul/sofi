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

    /* ==========================================================
     |  ENCUESTA PÚBLICA (renderizada por el backend)
     |  GET  /encuesta/{idcliente}/{iduser}
     |  POST /encuesta/{idcliente}/{iduser}
     |  Se sirve desde el servidor para que un F5 del cliente
     |  siempre traiga el estado real y actual desde la BD.
     ========================================================== */

    /**
     * GET /encuesta/{idcliente}/{iduser}
     * Página pública de la encuesta. Todo se resuelve contra la BD
     * en cada request (sin caché), así el F5 siempre muestra lo actual.
     */
    public function publicForm(Request $request, $idcliente, $iduser)
    {
        $ctx = $this->contexto((int)$idcliente, (int)$iduser);

        if ($ctx['error']) {
            return $this->sinCache(response()->view('encuesta.publica', $ctx, 404));
        }

        return $this->sinCache(response()->view('encuesta.publica', $ctx));
    }

    /**
     * POST /encuesta/{idcliente}/{iduser}
     * Guarda la respuesta y redirige al GET (patrón POST/Redirect/GET)
     * para que un F5 posterior no reenvíe el formulario.
     */
    public function publicStore(Request $request, $idcliente, $iduser)
    {
        $validated = $request->validate([
            'score'   => 'required|in:0,5,10',
            'comment' => 'nullable|string|max:500',
            'email'   => 'nullable|email|max:255',
            'declaro' => 'accepted',
        ], [
            'score.required'   => 'Selecciona una calificación.',
            'declaro.accepted' => 'Debes declarar que eres el cliente que recibió el servicio.',
        ]);

        $ctx = $this->contexto((int)$idcliente, (int)$iduser);
        $volver = redirect()->route('encuesta.publica', [
            'idcliente' => (int)$idcliente,
            'iduser'    => (int)$iduser,
        ]);

        if ($ctx['error']) {
            return $volver->with('error', $ctx['error']);
        }

        if ($ctx['respuesta']) {
            return $volver->with('error', 'Ya se registró una respuesta hoy para este cliente.');
        }

        $email = trim((string)($validated['email'] ?? ''));
        if ($email !== '' && $this->esCorreoDelRepartidor($email, $ctx['usuario'])) {
            return $volver->with('error', 'Esta encuesta es únicamente para el cliente: el correo del repartidor no puede responder.');
        }

        $this->registrarEncuesta($request, $ctx['cliente'], $ctx['usuario'], [
            'score'   => (int)$validated['score'],
            'comment' => $validated['comment'] ?? null,
            'email'   => $email ?: null,
        ]);

        return $volver->with('ok', '¡Gracias! Tu respuesta fue registrada.');
    }

    /**
     * Arma el contexto (cliente, repartidor, respuesta de hoy) para la vista.
     */
    private function contexto(int $clienteId, int $userId): array
    {
        $hoy = Carbon::now('America/La_Paz')->toDateString();

        $ctx = [
            'idcliente' => $clienteId,
            'iduser'    => $userId,
            'hoy'       => $hoy,
            'cliente'   => null,
            'usuario'   => null,
            'usuarioNombre' => null,
            'respuesta' => null,
            'error'     => null,
        ];

        if ($clienteId < 1 || $userId < 1) {
            $ctx['error'] = 'El enlace de la encuesta no es válido.';
            return $ctx;
        }

        $ctx['cliente'] = DB::table('tbclientes')->where('Cod_Aut', $clienteId)->first();
        if (!$ctx['cliente']) {
            $ctx['error'] = 'No encontramos el cliente de este enlace.';
            return $ctx;
        }

        $ctx['usuario'] = DB::table('personal')->where('CodAut', $userId)->first();
        if (!$ctx['usuario']) {
            $ctx['error'] = 'No encontramos al repartidor de este enlace.';
            return $ctx;
        }

        $ctx['usuarioNombre'] = $this->nombrePersonal($ctx['usuario']);

        // Respuesta de hoy (si ya existe, la vista muestra el agradecimiento)
        $ctx['respuesta'] = Encuesta::where('cliente_cod_aut', $clienteId)
            ->where('usuario_cod_aut', $userId)
            ->where('encuesta_date', $hoy)
            ->orderByDesc('id')
            ->first();

        return $ctx;
    }

    /**
     * Nombre completo del personal (los campos legados vienen con padding).
     */
    private function nombrePersonal($usr): string
    {
        return trim(implode(' ', array_filter(array_map('trim', [
            $usr->Nombre1 ?? '',
            $usr->Nombre2 ?? '',
            $usr->App1 ?? '',
            $usr->Apm ?? '',
        ]))));
    }

    /**
     * Anti-fraude: el correo del repartidor no puede responder su propia encuesta.
     */
    private function esCorreoDelRepartidor(string $email, $usr): bool
    {
        $correo = trim((string)($usr->correo ?? ''));
        if ($correo === '' || trim($email) === '') return false;

        return strcasecmp(trim($email), $correo) === 0;
    }

    /**
     * Crea la encuesta guardando el snapshot de cliente/repartidor + metadatos.
     */
    private function registrarEncuesta(Request $request, $cli, $usr, array $datos): Encuesta
    {
        return Encuesta::create([
            'cliente_cod_aut' => (int)$cli->Cod_Aut,
            'usuario_cod_aut' => (int)$usr->CodAut,

            'cliente_id'      => $cli->Id ?? null,
            'cliente_nombre'  => $cli->Nombres ?? null,
            'cliente_tel'     => $cli->Telf ?? null,
            'cliente_dir'     => $cli->Direccion ?? null,
            'cliente_zona'    => $cli->zona ?? null,
            'cliente_lat'     => $cli->Latitud ?? null,
            'cliente_lng'     => $cli->longitud ?? null,

            'usuario_ci'      => $usr->ci ?? null,
            'usuario_nombre'  => $this->nombrePersonal($usr) ?: null,
            'usuario_correo'  => trim((string)($usr->correo ?? '')) ?: null,
            'usuario_placa'   => $usr->placa ?? null,

            'score'           => (int)$datos['score'],
            'comment'         => $datos['comment'] ?? null,

            'encuesta_date'   => Carbon::now('America/La_Paz')->toDateString(),
            'email'           => $datos['email'] ?? null,

            'client_ip'       => $request->ip(),
            'origin_scheme'   => $request->getScheme(),
            'origin_host'     => $request->getHost(),
            'origin_path'     => $request->path(),
            'server_ip'       => $request->server('SERVER_ADDR') ?: null,
            'user_agent'      => $request->userAgent(),
            'referer'         => $request->headers->get('referer') ?: null,
        ]);
    }

    /**
     * La encuesta nunca debe quedar cacheada: cada F5 debe pegarle a la BD.
     */
    private function sinCache($response)
    {
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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

        $ctx = $this->contexto((int)$validated['idcliente'], (int)$validated['iduser']);

        if (!$ctx['cliente']) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }
        if (!$ctx['usuario']) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Anti-fraude: si el email que responde == correo del repartidor -> 403
        if (!empty($validated['email']) && $this->esCorreoDelRepartidor($validated['email'], $ctx['usuario'])) {
            return response()->json([
                'message' => 'Esta encuesta es únicamente para el cliente. El correo del repartidor no puede responder.'
            ], 403);
        }

        // Evitar duplicados (por día lógico)
        if ($ctx['respuesta']) {
            return response()->json([
                'message' => 'Ya existe una respuesta para este cliente y usuario hoy.'
            ], 409);
        }

        $encuesta = $this->registrarEncuesta($request, $ctx['cliente'], $ctx['usuario'], [
            'score'   => (int)$validated['score'],
            'comment' => $validated['comment'] ?? null,
            'email'   => $validated['email'] ?? null,
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

        $perPage = (int)($request->input('per_page', 10000));
        $perPage = max(1, min(200, $perPage));

        return response()->json($q->paginate($perPage));
    }
}
