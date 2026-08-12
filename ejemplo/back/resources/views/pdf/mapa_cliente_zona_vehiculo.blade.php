<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte por vehiculo</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111827; margin: 10px; }
        .header {
            border: 1px solid #d1d5db;
            background: #f3f4f6;
            padding: 10px;
            margin-bottom: 10px;
        }
        .title { font-size: 16px; font-weight: 700; margin: 0 0 3px 0; }
        .meta { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        .zona {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            color: #fff;
            font-weight: 700;
        }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">REPORTE PEDIDOS POR VEHICULO / ZONA</p>
        <p class="meta"><strong>Fecha:</strong> {{ $fecha }}</p>
        <p class="meta"><strong>Camion:</strong> {{ $camion->name }} {{ $camion->placa ? ('(' . $camion->placa . ')') : '' }}</p>
        <p class="meta"><strong>Total pedidos:</strong> {{ $pedidos->count() }} | <strong>Monto:</strong> {{ number_format((float) $total, 2) }} Bs</p>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 8%;">Pedido</th>
            <th style="width: 9%;">Horario</th>
            <th style="width: 17%;">Cliente</th>
            <th style="width: 18%;">Direccion</th>
            <th style="width: 10%;">Telefono</th>
            <th style="width: 10%;">Vendedor</th>
            <th style="width: 10%;">Zona</th>
            <th style="width: 7%;">Items</th>
            <th style="width: 8%;" class="right">Importe</th>
        </tr>
        </thead>
        <tbody>
        @foreach($pedidos as $idx => $pedido)
            @php
                $zonaColor = $pedido->zona->color ?? '#6b7280';
            @endphp
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>#{{ $pedido->id }}</td>
                <td>{{ $pedido->hora ?? '-' }}</td>
                <td>{{ $pedido->cliente->nombre ?? '-' }}</td>
                <td>{{ $pedido->cliente->direccion ?? '-' }}</td>
                <td>{{ $pedido->cliente->telefono ?? '-' }}</td>
                <td>{{ $pedido->user->name ?? '-' }}</td>
                <td>
                    <span class="zona" style="background: {{ $zonaColor }};">
                        {{ $pedido->zona->nombre ?? 'SIN ZONA' }}
                    </span>
                </td>
                <td>{{ $pedido->detalles->count() }}</td>
                <td class="right">{{ number_format((float) $pedido->total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

