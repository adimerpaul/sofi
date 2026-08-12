<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte pedidos</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; color: #1f2937; }
        .page { page-break-after: always; padding: 10px; }
        .page:last-child { page-break-after: auto; }
        .header { border: 1px solid #d1d5db; padding: 8px; margin-bottom: 8px; background: #f8fafc; }
        .title { font-size: 18px; font-weight: 700; text-align: center; margin: 0 0 4px 0; }
        .subtitle { text-align: center; margin: 0; font-size: 11px; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; }
        .label { font-weight: 700; color: #111827; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            color: #fff; font-weight: 700; font-size: 10px;
        }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th, .items td { border: 1px solid #d1d5db; padding: 5px; }
        .items th { background: #e5e7eb; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
@foreach($pedidos as $pedido)
    @php
        $zonaColor = $pedido->zona->color ?? '#6b7280';
        $textColor = '#ffffff';
    @endphp
    <section class="page">
        <div class="header">
            <p class="title">SOLICITUD DE PEDIDO</p>
            <p class="subtitle">Fecha reporte: {{ $fecha }} | Pedido #{{ $pedido->id }}</p>
        </div>

        <table class="grid">
            <tr>
                <td><span class="label">Cliente:</span> {{ $pedido->cliente->nombre ?? '-' }}</td>
                <td><span class="label">CINIT:</span> {{ $pedido->cliente->codcli ?? ($pedido->cliente->ci ?? '-') }}</td>
                <td><span class="label">Horario:</span> {{ $pedido->hora ?? '-' }}</td>
                <td><span class="label">Estado:</span> {{ $pedido->estado }}</td>
            </tr>
            <tr>
                <td><span class="label">Direccion:</span> {{ $pedido->cliente->direccion ?? '-' }}</td>
                <td><span class="label">Telefono:</span> {{ $pedido->cliente->telefono ?? '-' }}</td>
                <td><span class="label">Territorio:</span> {{ $pedido->cliente->territorio ?? '-' }}</td>
                <td>
                    <span class="badge" style="background: {{ $zonaColor }}; color: {{ $textColor }};">
                        {{ $pedido->zona->nombre ?? 'SIN ZONA' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td><span class="label">Vendedor:</span> {{ $pedido->user->name ?? '-' }}</td>
                <td><span class="label">Camion:</span> {{ $pedido->usuarioCamion->name ?? 'SIN RUTA' }}</td>
                <td><span class="label">Placa:</span> {{ $pedido->usuarioCamion->placa ?? '-' }}</td>
                <td><span class="label">Total:</span> {{ number_format((float) $pedido->total, 2) }} Bs</td>
            </tr>
            <tr>
                <td colspan="4"><span class="label">Observaciones:</span> {{ $pedido->observaciones ?: '-' }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th style="width: 14%;">Codigo</th>
                <th>Producto</th>
                <th style="width: 12%;" class="right">Cantidad</th>
                <th style="width: 14%;" class="right">Precio</th>
                <th style="width: 14%;" class="right">Subtotal</th>
            </tr>
            </thead>
            <tbody>
            @foreach($pedido->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto->codigo ?? ('#' . $detalle->producto_id) }}</td>
                    <td>{{ $detalle->producto->nombre ?? '-' }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 2, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $detalle->precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $detalle->total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endforeach
</body>
</html>

