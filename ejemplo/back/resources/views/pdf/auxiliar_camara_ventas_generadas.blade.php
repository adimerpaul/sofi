<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auxiliar camara ventas generadas</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 10px; color: #1f2937; }
        .title { text-align: center; font-size: 17px; font-weight: 700; margin: 0; }
        .sub { text-align: center; margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: middle; }
        th { background: #e5e7eb; text-align: left; }
        .right { text-align: right; }
        .total td { font-weight: 700; background: #f3f4f6; }
    </style>
</head>
<body>
<p class="title">AUXILIAR DE CAMARA - VENTAS GENERADAS</p>
<p class="sub">Fecha: {{ $fecha }} | Ventas: {{ $pedidos->count() }}</p>

<table>
    <thead>
    <tr>
        <th style="width: 7%;">Pedido</th>
        <th style="width: 7%;">Venta</th>
        <th>Cliente</th>
        <th style="width: 14%;">Vendedor</th>
        <th style="width: 14%;">Camion</th>
        <th style="width: 13%;">Zona</th>
        <th style="width: 10%;">Estado</th>
        <th style="width: 10%;" class="right">Total Bs</th>
    </tr>
    </thead>
    <tbody>
    @foreach($pedidos as $pedido)
        <tr>
            <td>#{{ $pedido->id }}</td>
            <td>#{{ $pedido->venta_id }}</td>
            <td>{{ $pedido->cliente->nombre ?? '-' }}</td>
            <td>{{ $pedido->user->name ?? '-' }}</td>
            <td>{{ $pedido->usuarioCamion->name ?? '-' }}</td>
            <td>{{ $pedido->zona->nombre ?? '-' }}</td>
            <td>{{ $pedido->venta->estado ?? '-' }}</td>
            <td class="right">{{ number_format((float) ($pedido->venta->total ?? 0), 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="7">TOTAL GENERAL</td>
        <td class="right">{{ number_format((float) $totalVentas, 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
