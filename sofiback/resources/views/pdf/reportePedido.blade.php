<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Pedido</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .page {
            page-break-after: always;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        .header p {
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
@foreach ($pedidos as $pedido)
    <div class="page">
        <div class="header">
            <h1 style="font-size: 20px; margin-bottom: 10px;">Solicitud de Pedido</h1>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td colspan="2" style="padding: 4px; font-weight: bold;">Número de Pedido: {{ $pedido['pedido']->NroPed }}</td>
                    <td colspan="2" style="padding: 4px;"></td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold;">Cliente:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->cliente->Nombres }}</td>
{{--                </tr>--}}
{{--                <tr>--}}
                    <td style="padding: 4px; font-weight: bold;">Fecha de Pedido:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->fecha }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold;">Dirección:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->cliente->Direccion }}</td>
{{--                </tr>--}}
{{--                <tr>--}}
                    <td style="padding: 4px; font-weight: bold;">Zona:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->cliente->zona }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="padding: 4px; font-weight: bold;">
                        Encargado:
                        {{ $pedido['pedido']->user->Nombre1 }}
                        {{ $pedido['pedido']->user->App1 }}
                    </td>
{{--                    <td style="padding: 4px;">{{ $pedido['pedido']->CIfunc }}</td>--}}
                </tr>
            </table>
        </div>
        <table>
            <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Subtotal</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($pedido['productos'] as $producto)
                <tr>
                    <td>{{ $producto->cod_prod }}</td>
                    <td>{{ isset($producto->producto) ? $producto->producto->Producto : '' }}</td>
                    <td>{{ $producto->Cant }}</td>
                    <td>{{ $producto->precio }}</td>
                    <td>{{ $producto->subtotal }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
