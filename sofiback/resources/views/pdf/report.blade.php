<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Encuestas</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color:#666; }
        .chips span { display:inline-block; padding:2px 6px; margin-right:6px; border-radius:4px; color:#fff; font-size:11px;}
        .chip10 { background:#21ba45; }  /* positive */
        .chip5  { background:#f2c037; }  /* amber */
        .chip0  { background:#c10015; }  /* negative */

        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border:1px solid #ddd; padding:6px 8px; vertical-align: top; }
        th { background:#f5f5f5; text-align: left; }
        .w-15 { width:15%; }
        .w-20 { width:20%; }
        .w-25 { width:25%; }
        .nowrap { white-space: nowrap; }
        .small { font-size: 11px; color:#555; }
        .header { display:flex; justify-content:space-between; align-items:flex-start; }
        .meta { margin-top: 2px; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>Reporte de Encuestas</h1>
        <div class="meta muted small">
            Generado: {{ $now->format('Y-m-d H:i') }} (America/La_Paz)
        </div>
        <div class="meta small">
            @if($filters['from']) <b>Desde:</b> {{ $filters['from'] }} @endif
            @if($filters['to']) &nbsp; <b>Hasta:</b> {{ $filters['to'] }} @endif
            @if($filters['usuario']) &nbsp; <b>Usuario:</b> {{ $filters['usuario'] }} @endif
            @if(!is_null($filters['score'])) &nbsp; <b>Score:</b> {{ $filters['score'] }} @endif
        </div>
    </div>
    <div class="chips">
        <span class="chip10">10: {{ $t10 }}</span>
        <span class="chip5">5: {{ $t5 }}</span>
        <span class="chip0">0: {{ $t0 }}</span>
        <span class="muted small">Total: {{ $total }}</span>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th class="w-15">Fecha (encuesta / creación)</th>
        <th class="w-25">Cliente</th>
        <th class="w-20">Usuario</th>
        <th class="w-15">Score</th>
        <th>Comentario</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $r)
        <tr>
            <td class="small">
                <div>{{ $r->encuesta_date ?? '-' }}</div>
                <div class="muted">{{ optional($r->created_at)->format('Y-m-d H:i') }}</div>
            </td>
            <td class="small">
                <div><b>{{ $r->cliente_nombre ?? '-' }}</b></div>
                <div class="muted">Cod_Aut: {{ $r->cliente_cod_aut }} · ID: {{ $r->cliente_id ?? '-' }}</div>
                <div class="muted">{{ $r->cliente_dir ?? '-' }} ({{ $r->cliente_zona ?? '-' }})</div>
            </td>
            <td class="small">
                <div><b>{{ $r->usuario_nombre ?? '-' }}</b></div>
                <div class="muted">CodAut: {{ $r->usuario_cod_aut }} · CI: {{ $r->usuario_ci ?? '-' }}</div>
                <div class="muted">{{ $r->usuario_correo ?? '-' }}</div>
            </td>
            <td>
                @if($r->score === 10)
                    <span class="chip10">10</span>
                @elseif($r->score === 5)
                    <span class="chip5">5</span>
                @else
                    <span class="chip0">{{ $r->score }}</span>
                @endif
            </td>
            <td class="small">{{ $r->comment ?? '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="small muted">Sin datos para el filtro seleccionado.</td>
        </tr>
    @endforelse
    </tbody>
</table>

</body>
</html>
