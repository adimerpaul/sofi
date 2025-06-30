<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Productos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #999;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        .date {
            text-align: right;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="title">REPORTE DE PRODUCTOS TOTALES</div>
<div class="date">Fecha: {{ $fecha }}</div>

<table>
    <thead>
    <tr>
        <th>Código</th>
        <th>Producto</th>
        <th>Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($productos as $prod)
        <tr>
            <td>{{ $prod['codigo'] }}</td>
            <td>{{ $prod['nombre'] }}</td>
            <td>{{ $prod['total'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
