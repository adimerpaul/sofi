<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificacion ventas</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 10px; color: #111827; }
        .title { font-size: 16px; font-weight: 700; text-align: center; margin: 0; }
        .sub { text-align: center; margin: 2px 0 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 3px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        .compact { font-size: 8px; line-height: 1.15; }
        .right { text-align: right; }
        .center { text-align: center; }
        .ok { color: #166534; font-weight: 700; }
        .no { color: #991b1b; font-weight: 700; }
    </style>
</head>
<body>
<p class="title">VERIFICACION DE VENTAS DEL DIA</p>
<p class="sub">Fecha: {{ $fecha }} | Filtro verificado: {{ $filtroVerificado }} | Total: {{ $ventas->count() }}</p>

<table>
    <thead>
    <tr>
        <th style="width: 7%;">Venta</th>
        <th style="width: 7%;">Pedido</th>
        <th style="width: 10%;">Hora</th>
        <th style="width: 12%;">Camion</th>
        <th style="width: 12%;">Vendedor</th>
        <th style="width: 14%;">Cliente</th>
        <th>Productos</th>
        <th style="width: 8%;">Fact.</th>
        <th style="width: 10%;">Verif.</th>
        <th style="width: 12%;">Quien verifico</th>
    </tr>
    </thead>
    <tbody>
    @foreach($ventas as $venta)
        <tr>
            <td class="center">{{ $venta->id }}</td>
            <td class="center">{{ $venta->pedido_id ?: '-' }}</td>
            <td class="center">{{ trim(($venta->fecha ?? '') . ' ' . ($venta->hora ?? '')) }}</td>
            <td>{{ $venta->pedido?->usuarioCamion?->name ?? 'SIN CAMION' }}{{ $venta->pedido?->usuarioCamion?->placa ? ' / ' . $venta->pedido?->usuarioCamion?->placa : '' }}</td>
            <td>{{ $venta->pedido?->user?->name ?? $venta->user?->name ?? '-' }}</td>
            <td>{{ $venta->pedido?->cliente?->nombre ?? $venta->cliente?->nombre ?? '-' }}</td>
            <td class="compact">
                @foreach($venta->ventaDetalles as $detalle)
                    {{ $detalle->producto?->codigo ?: ('#' . $detalle->producto_id) }} {{ $detalle->nombre ?: ($detalle->producto?->nombre ?? '-') }} x {{ rtrim(rtrim(number_format((float) $detalle->cantidad, 2, '.', ''), '0'), '.') }}@if(!$loop->last)<br>@endif
                @endforeach
            </td>
            <td class="center {{ $venta->facturado ? 'ok' : 'no' }}">{{ $venta->facturado ? 'SI' : 'NO' }}</td>
            <td class="center {{ $venta->verificado ? 'ok' : 'no' }}">
                {{ $venta->verificado ? 'SI' : 'NO' }}
                @if($venta->verificado_at)
                    <br>{{ $venta->verificado_at->format('d/m H:i') }}
                @endif
            </td>
            <td>{{ $venta->verificador?->name ?? '-' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
