<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Pedido</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 0;
            padding: 0;
            border: 0;
        }
        .page {
            page-break-after: always;
            /*padding: 10px;*/
        }
        .header {
            /*text-align: center;*/
            /*margin-bottom: 10px;*/
        }
        .header h1 {
            /*font-size: 24px;*/
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
            padding: 4px;
            text-align: left;
        }
        table th {
            background-color: #f4f4f4;
        }
        .bold {
            font-weight: bold;
        }
        .f-20 {
            font-size: 20px;
        }
        .f-28 {
            font-size: 28px;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
@php
    function colorPlaca($placa,$vehiculos) {
        $color = '';
        if (isset($vehiculos)) {
            foreach ($vehiculos as $vehiculo) {
                if ($vehiculo->placa == $placa) {
                    $color = $vehiculo->colorStyle;
                    break;
                }
            }
        }
        return $color;
    }
@endphp
@foreach ($pedidos as $index => $pedido)
    <div class="@if ($index < count($pedidos) - 1) page @endif">
        <div class="header">
            <table style="width: 100%;">
                <tr>
                <td>
                    <span class="bold"></span>
                    <span class="bold f-28">
{{--                        {{ $pedido['pedido']->cliente->Nombres }}--}}
                        {{ $pedido['pedido']->bonificacionAprovacion != null ? $pedido['bonificacionCliente']->Nombres : $pedido['pedido']->cliente->Nombres }}
                    </span>
                </td>
                    <td style="text-align: right; {{ $pedido['pedido']->colorStyle }}">
                        <span class="bold">Nro pedido:</span>
                        <span class="bold f-28">{{ $pedido['pedido']->NroPed }}</span>
                    </td>
                </tr>
            </table>
            <hr>
            <div class="f-20 bold center">SOLICITUD DE PEDIDO</div>
            <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
                <tr>
                    <td colspan="2" style="padding: 4px; font-weight: bold;">Número de Pedido: {{ $pedido['pedido']->NroPed }}</td>
                    <td style="padding: 4px;">
                        <span class="bold">Facturado:</span>
                        {{ $pedido['pedido']->fact}}
                    </td>
                    <td>
                        <span class="bold">Pago:</span>
                        {{ $pedido['pedido']->pago}}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold;">Cliente:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->cliente->Nombres }}</td>
                    <td style="padding: 4px; font-weight: bold;">Fecha de Pedido:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->fecha }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold;">Horario:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->horario }}</td>
                    <td style="padding: 4px; font-weight: bold;">Comentario:</td>
                    <td style="padding: 4px;">
{{--                        {{ $pedido['pedido']->comentario }}--}}
                        {{ $pedido['pedido']->bonificacionAprovacion != null ? $pedido['pedido']->comentario.' '.$pedido['pedido']->cliente->Nombres : $pedido['pedido']->comentario }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 4px; font-weight: bold;">Dirección:</td>
                    <td style="padding: 4px;">{{ $pedido['pedido']->cliente->Direccion }}</td>
                    <td style="padding: 4px; font-weight: bold;">Zona: {{ $pedido['pedido']->cliente->zona }}</td>
                    <td style="padding: 4px; {{ $pedido['pedido']->colorStyle }}">
                        Placa:
                        {{ $pedido['pedido']->placa }}
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding: 4px; font-weight: bold;">
                        <div>
                            Encargado:
                            {{ $pedido['pedido']->user->Nombre1 }}
                            {{ $pedido['pedido']->user->App1 }}
                        </div>
                        <div>
                            Observaciones:
                            {{ $pedido['pedido']->comentario }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <table>
            <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>
                    Cant. complementaria
                </th>
                <th>Precio</th>
                <th>Subtotal</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($pedido['productos'] as $producto)
                <tr>
{{--                    <td>{{ $producto->cod_prod }}</td>--}}
{{--                    <td>{{ isset($producto->producto) ? $producto->producto->Producto : '' }}</td>--}}
{{--                    <td>{{ $producto->Cant }}</td>--}}
{{--                    <td>{{ $producto->Canttxt }}</td>--}}
{{--                    <td>{{ $producto->precio }}</td>--}}
{{--                    <td>{{ $producto->subtotal }}</td>--}}
                    <td>{{ $producto['cod_prod'] }}</td>
                    <td>{{ $producto['producto'] }}</td>
                    <td>{{ $producto['Cant'] }}</td>
                    <td>{{ $producto['Canttxt'] }}</td>
                    <td>{{ $producto['precio'] }}</td>
                    <td>{{ $producto['subtotal'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
