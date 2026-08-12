<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Clientes sin pedidos</title>
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
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            width: 100px;
        }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
        }
        .meta {
            text-align: right;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="header">
    <img src="{{ public_path('logo-sofia.png') }}" class="logo">
    <div class="meta">
        <div>Generado por: <strong>{{ $usuario->Nombre1 }}</strong></div>
        <div>Fecha: {{ $fecha }}</div>
    </div>
</div>

<div class="title">Clientes que no realizaron pedido en el rango {{ $ini }} - {{ $fin }}</div>

<table>
    <thead>
    <tr>
        <th>CI</th>
        <th>Nombre</th>
        <th>Dirección</th>
        <th>Teléfono</th>
        <th>Última Compra</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($clientes as $cli)
        <tr>
            <td>{{ $cli->Id }}</td>
            <td>{{ $cli->Nombres }}</td>
            <td>{{ $cli->Direccion }}</td>
            <td>{{ $cli->Telf }}</td>
            <td>{{ $cli->ultima_compra ?? 'Nunca' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
