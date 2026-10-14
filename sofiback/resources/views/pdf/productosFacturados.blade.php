@php
    $cant = function ($valor) {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ','), '0'), '.');
    };
    $criterio = ['importe' => 'importe', 'peso' => 'peso', 'cantidad' => 'cantidad'][$orden];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos facturados {{ $desde }} a {{ $hasta }}</title>
    <style>
        {!! $estilos !!}

        .resumen { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 8px -5px 0 }
        .resumen td { border: 1px solid #ddd; border-radius: 3px; padding: 5px 7px; width: 25% }
        .resumen .et { font-size: 7.5px; color: #777; text-transform: uppercase; letter-spacing: .4px }
        .resumen .val { font-size: 15px; font-weight: bold; color: #37474f; margin-top: 1px }

        .orden { font-size: 8px; color: #607d8b; margin-top: 6px }
        .unidad { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 7.5px;
                  font-weight: bold; background: #eceff1; color: #455a64 }
        .unidad-KG { background: #fff3e0; color: #e65100 }
        .unidad-CAJA { background: #e8eaf6; color: #283593 }
        .fuerte { font-weight: bold }
        .detalle tr.total td { background: #37474f; color: #fff; font-weight: bold; font-size: 9.5px; border: 0 }
    </style>
</head>
<body>

{!! $cabecera !!}

<table class="resumen">
    <tr>
        <td><div class="et">Productos</div><div class="val">{{ $totales['productos'] }}</div></td>
        <td><div class="et">Comprobantes</div><div class="val">{{ $totales['comprobantes'] }}</div></td>
        <td><div class="et">Peso kg</div><div class="val">{{ $cant($totales['peso']) }}</div></td>
        <td><div class="et">Importe</div><div class="val">Bs {{ number_format($totales['importe'], 2) }}</div></td>
    </tr>
</table>

<div class="orden">Ordenado de mayor a menor por {{ $criterio }} · facturas y vouchers no anulados</div>

<table class="detalle">
    <thead>
    <tr>
        <th style="width:20px">N°</th>
        <th style="width:52px; text-align:left">Código</th>
        <th style="text-align:left">Producto</th>
        <th style="width:38px">Unidad</th>
        <th style="width:56px" class="r">Cantidad</th>
        <th style="width:60px" class="r">Peso kg</th>
        <th style="width:70px" class="r">Importe Bs</th>
        <th style="width:38px" class="r">%</th>
        <th style="width:40px">Comp.</th>
        <th style="width:40px">Clientes</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($filas as $f)
        <tr class="{{ $f['puesto'] % 2 === 0 ? 'par' : '' }}">
            <td class="c gris">{{ $f['puesto'] }}</td>
            <td class="cod">{{ $f['cod_prod'] }}</td>
            <td>{{ $f['producto'] }}</td>
            <td class="c"><span class="unidad unidad-{{ $f['unidad'] }}">{{ $f['unidad'] }}</span></td>
            <td class="r {{ $orden === 'cantidad' ? 'fuerte' : '' }}">{{ $cant($f['cantidad']) }}</td>
            <td class="r {{ $orden === 'peso' ? 'fuerte' : '' }}">{{ $cant($f['peso']) }}</td>
            <td class="r {{ $orden === 'importe' ? 'fuerte' : '' }}">{{ number_format($f['importe'], 2) }}</td>
            <td class="r gris">{{ number_format($f['porcentaje'], 1) }}</td>
            <td class="c">{{ $f['comprobantes'] }}</td>
            <td class="c">{{ $f['clientes'] }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="c gris" style="padding:18px">No hay productos facturados en este rango</td></tr>
    @endforelse
    @if (count($filas))
        <tr class="total">
            <td colspan="4">TOTAL</td>
            <td class="r">{{ $cant($totales['cantidad']) }}</td>
            <td class="r">{{ $cant($totales['peso']) }}</td>
            <td class="r">{{ number_format($totales['importe'], 2) }}</td>
            <td class="r">100</td>
            <td class="c">{{ $totales['comprobantes'] }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>

<div class="pie">
    <div class="legal">
        {{ $empresa }} &middot; Productos facturados del {{ date('d/m/Y', strtotime($desde)) }}
        al {{ date('d/m/Y', strtotime($hasta)) }} &middot; generado el {{ date('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
