<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte productos totales</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111827; margin: 10px; }
        .header {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .title { margin: 0 0 4px 0; font-size: 16px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: middle; }
        th { background: #e5e7eb; text-align: left; }
        .imgbox {
            width: 34px;
            height: 34px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-align: center;
            line-height: 34px;
            overflow: hidden;
        }
        .imgbox img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .right { text-align: right; }
        .total-row td { font-weight: 700; background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">REPORTE DE PRODUCTOS TOTALES</p>
        <div><strong>Fecha:</strong> {{ $fecha }}</div>
        <div><strong>Total productos:</strong> {{ $productos->count() }}</div>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width: 7%;">Img</th>
            <th style="width: 13%;">Codigo</th>
            <th>Producto</th>
            <th style="width: 14%;" class="right">Cantidad total</th>
            <th style="width: 15%;" class="right">Importe total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($productos as $p)
            <tr>
                <td>
                    <div class="imgbox">
                        @if($p['imagen_path'])
                            <img src="{{ $p['imagen_path'] }}" alt="img">
                        @else
                            -
                        @endif
                    </div>
                </td>
                <td>{{ $p['codigo'] }}</td>
                <td>{{ $p['nombre'] }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $p['cantidad_total'], 2, '.', ''), '0'), '.') }}</td>
                <td class="right">{{ number_format((float) $p['importe_total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">TOTAL GENERAL</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $cantidadTotal, 2, '.', ''), '0'), '.') }}</td>
            <td class="right">{{ number_format((float) $importeTotal, 2) }}</td>
        </tr>
        </tbody>
    </table>
</body>
</html>

