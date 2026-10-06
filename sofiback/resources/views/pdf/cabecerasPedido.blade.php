<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cabeceras de pedido</title>
    <style>
        @page { margin: 30px 30px; }
        body { font-family: Arial, sans-serif; margin: 0; }
        .titulo { font-size: 11px; font-weight: bold; color: #555; margin-bottom: 8px; }
        /* Una cabecera por pedido, separadas para poder recortarlas. */
        .cabecera {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .cabecera td { border: 1px solid #ccc; padding: 6px 8px; vertical-align: middle; }
        .cliente { font-size: 24px; font-weight: bold; }
        .numero { width: 34%; text-align: right; white-space: nowrap; }
        .numero .rotulo { font-size: 10px; font-weight: bold; }
        .numero .nro { font-size: 28px; font-weight: bold; }
        .tipo { display: block; font-size: 10px; font-weight: bold; letter-spacing: 1px; }
    </style>
</head>
<body>
<div class="titulo">{{ $tipoNombre }} · {{ $fecha }} · {{ count($pedidos) }} pedidos</div>
@foreach ($pedidos as $p)
    <table class="cabecera">
        <tr>
            <td class="cliente">{{ $p['cliente'] }}</td>
            <td class="numero" style="{{ $p['color'] }}">
                <span class="tipo">{{ $tipoNombre }}</span>
                <span class="rotulo">Nro pedido:</span>
                <span class="nro">{{ $p['nro'] }}</span>
            </td>
        </tr>
    </table>
@endforeach
</body>
</html>
