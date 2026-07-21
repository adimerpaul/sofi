<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auxiliar camara pedidos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 10px; color: #1f2937; }
        .title { font-size: 18px; font-weight: 700; text-align: center; margin: 0; }
        .sub { text-align: center; margin: 0 0 8px 0; }
        .card { border: 1px solid #d1d5db; border-radius: 8px; padding: 8px; margin-bottom: 10px; page-break-inside: avoid; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .grid td { border: 1px solid #e5e7eb; padding: 4px; }
        .label { font-weight: 700; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .items th, .items td { border: 1px solid #d1d5db; padding: 4px; }
        .items th { background: #e5e7eb; text-align: left; }
        .imgbox {
            width: 30px; height: 30px; border: 1px solid #d1d5db;
            border-radius: 4px; text-align: center; line-height: 30px; overflow: hidden;
        }
        .imgbox img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .right { text-align: right; }
    </style>
</head>
<body>
<p class="title">AUXILIAR DE CAMARA - PEDIDOS DEL DIA</p>
<p class="sub">Fecha: {{ $fecha }} | Total pedidos: {{ $pedidos->count() }}</p>

@foreach($pedidos as $pedido)
    @php
        $zonaColor = $pedido->zona->color ?? '#6b7280';
    @endphp
    <div class="card">
        <table class="grid">
            <tr>
                <td><span class="label">Pedido:</span> #{{ $pedido->id }}</td>
                <td><span class="label">Horario:</span> {{ $pedido->hora ?? '-' }}</td>
                <td><span class="label">Estado aux:</span> {{ $pedido->auxiliar_estado ?? 'PENDIENTE' }}</td>
                <td class="right"><span class="label">Total:</span> {{ number_format((float) $pedido->total, 2) }} Bs</td>
            </tr>
            <tr>
                <td><span class="label">Cliente:</span> {{ $pedido->cliente->nombre ?? '-' }}</td>
                <td><span class="label">Cod/Ci:</span> {{ $pedido->cliente->codcli ?? ($pedido->cliente->ci ?? '-') }}</td>
                <td><span class="label">Vendedor:</span> {{ $pedido->user->name ?? '-' }}</td>
                <td><span class="label">Camion:</span> {{ $pedido->usuarioCamion->name ?? 'SIN RUTA' }}</td>
            </tr>
            <tr>
                <td><span class="label">Direccion:</span> {{ $pedido->cliente->direccion ?? '-' }}</td>
                <td><span class="label">Telefono:</span> {{ $pedido->cliente->telefono ?? '-' }}</td>
                <td><span class="label">Territorio:</span> {{ $pedido->cliente->territorio ?? '-' }}</td>
                <td style="background: {{ $zonaColor }}; color: #fff;"><span class="label">Zona:</span> {{ $pedido->zona->nombre ?? 'SIN ZONA' }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th style="width: 7%;">Img</th>
                <th style="width: 12%;">Codigo</th>
                <th>Producto</th>
                <th style="width: 8%;">Tipo</th>
                <th style="width: 11%;" class="right">Cantidad</th>
                <th style="width: 11%;" class="right">Precio</th>
                <th style="width: 12%;" class="right">Subtotal</th>
            </tr>
            </thead>
            <tbody>
            @foreach($pedido->detalles as $detalle)
                <tr>
                    <td>
                        <div class="imgbox">
                            @if(!empty($detalle->imagen_data_url))
                                <img src="{{ $detalle->imagen_data_url }}" alt="img">
                            @else
                                -
                            @endif
                        </div>
                    </td>
                    <td>{{ $detalle->producto->codigo ?? ('#' . $detalle->producto_id) }}</td>
                    <td>{{ $detalle->producto->nombre ?? '-' }}</td>
                    <td>{{ strtoupper((string) ($detalle->producto->tipo ?? 'NORMAL')) }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $detalle->cantidad, 2, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $detalle->precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $detalle->total, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
