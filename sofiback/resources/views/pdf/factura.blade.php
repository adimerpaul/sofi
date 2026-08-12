<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura N° {{ $comanda }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        .header {
            background-color: #dc3545;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        .info, .resumen {
            margin-top: 10px;
        }
        .info-table td {
            padding: 2px 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th {
            background-color: #eee;
            text-align: center;
        }
        td {
            padding: 4px;
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            text-align: left;
        }
        .resumen td {
            padding: 4px;
            border: none;
        }
        .firma {
            margin-top: 30px;
            text-align: center;
        }
        .firma-line {
            margin-top: 40px;
            border-top: 1px solid black;
            width: 60%;
            margin-left: auto;
            margin-right: auto;
        }
        .respaldo {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="header">
    ALMACÉN SOFIA - BOLETA DE ENTREGA - ORIGINAL
</div>

<table class="info-table">
    <tr>
        <td><b>CI/NIT:</b> 3544875019</td>
        <td><b>Telf.:</b> 5289343</td>
        <td><b>F. Emisión:</b> {{ \Carbon\Carbon::parse($cliente->FechaEntreg)->format('d/m/Y') }}</td>
        <td><b>Zona:</b> CENTRO</td>
    </tr>
    <tr>
        <td colspan="2"><b>Cliente:</b> {{ $cliente->Nombres }}</td>
        <td colspan="2"><b>Dirección:</b> {{ $cliente->Direccion }}</td>
    </tr>
    <tr>
        <td colspan="2"><b>Vendedor:</b> {{ $usuario->Nombre1 ?? 'No Asignado' }}</td>
        <td><b>Nro Pedido:</b> {{ $comanda }}</td>
        <td><b>Fecha Entrega:</b> {{ \Carbon\Carbon::parse($cliente->FechaEntreg)->format('d/m/Y') }}</td>
    </tr>
</table>

<table>
    <thead>
    <tr>
        <th>CANT</th>
        <th>CÓDIGO</th>
        <th>CONCEPTO</th>
        <th>UNID</th>
        <th>P. BRUTO</th>
        <th>CJS</th>
        <th>KG</th>
        <th>P. NETO</th>
        <th>P. UNIT</th>
        <th>TOTAL</th>
    </tr>
    </thead>
    <tbody>
    @php $total = 0; @endphp
    @foreach ($pedido as $item)
        @php
            $subtotal = $item->cant * $item->PVentUnit;
            $total += $subtotal;
        @endphp
        <tr>
            <td>{{ number_format($item->cant, 2) }}</td>
            <td>{{ $item->cod_prod }}</td>
            <td style="text-align:left">{{ $item->Producto }}</td>
            <td>U</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>{{ number_format($item->cant, 2) }}</td>
            <td>{{ number_format($item->PVentUnit, 2) }}</td>
            <td>{{ number_format($subtotal, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="resumen" width="100%">
    <tr>
        <td width="70%"><strong>LITERAL:</strong> {{ $literal }}</td>
        <td><strong>SUB. TOT Bs.:</strong></td>
        <td>{{ number_format($total, 2) }}</td>
    </tr>
    <tr>
        <td></td>
        <td><strong>DESCT. Bs.:</strong></td>
        <td>0.00</td>
    </tr>
    <tr>
        <td></td>
        <td><strong>TOTAL Bs.:</strong></td>
        <td>{{ number_format($total, 2) }}</td>
    </tr>
</table>

<div class="firma">
    <br><br>
    <div class="firma-line"></div>
    CI: ___________________ &nbsp;&nbsp;&nbsp; Nombre: ___________________ &nbsp;&nbsp;&nbsp; Firma: ___________________
</div>

<div class="respaldo">
    “Respalde su cancelación del presente, con el sello y/o recibo de cobranza”
</div>

</body>
</html>
