<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auxiliar camara productos</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 10px; color: #1f2937; }
        .title { text-align: center; font-size: 17px; font-weight: 700; margin: 0; }
        .sub { text-align: center; margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: middle; }
        th { background: #e5e7eb; text-align: left; }
        .right { text-align: right; }
        .imgbox {
            width: 36px; height: 36px; border: 1px solid #d1d5db;
            border-radius: 4px; text-align: center; line-height: 36px; overflow: hidden;
        }
        .imgbox img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .total td { font-weight: 700; background: #f3f4f6; }
    </style>
</head>
<body>
<p class="title">AUXILIAR DE CAMARA - PRODUCTOS TOTALES</p>
<p class="sub">Fecha: {{ $fecha }} | Productos: {{ $productos->count() }}</p>

<table>
    <thead>
    <tr>
        <th style="width: 7%;">Img</th>
        <th style="width: 13%;">Codigo</th>
        <th>Producto</th>
        <th style="width: 10%;">Tipo</th>
        <th style="width: 14%;" class="right">Cantidad total</th>
        @if($includeClientes)
            <th style="width: 25%;">Clientes</th>
        @endif
        <th style="width: 15%;" class="right">Importe total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($productos as $p)
        <tr>
            <td>
                <div class="imgbox">
                    @if($p['imagen_data_url'])
                        <img src="{{ $p['imagen_data_url'] }}" alt="img">
                    @else
                        -
                    @endif
                </div>
{{--                <pre>{{json_encode($p['imagen_data_url'])}}</pre>--}}
            </td>
            <td>{{ $p['codigo'] }}</td>
            <td>{{ $p['nombre'] }}</td>
            <td>{{ $p['tipo'] }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $p['cantidad_total'], 2, '.', ''), '0'), '.') }}</td>
            @if($includeClientes)
                <td>{{ $p['clientes'] }}</td>
            @endif
            <td class="right">{{ number_format((float) $p['importe_total'], 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="{{ $includeClientes ? 4 : 4 }}">TOTAL GENERAL</td>
        <td class="right">{{ rtrim(rtrim(number_format((float) $cantidadTotal, 2, '.', ''), '0'), '.') }}</td>
        @if($includeClientes)
            <td></td>
        @endif
        <td class="right">{{ number_format((float) $importeTotal, 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
