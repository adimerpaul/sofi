@php
    $cant = function ($valor) {
        return rtrim(rtrim(number_format((float) $valor, 3, '.', ','), '0'), '.');
    };
    $nro = 0;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $placa !== '' ? 'Carga ' . $placa : 'Productos totales' }} {{ $fecha }}</title>
    <style>
        {!! $estilos !!}

        /* Franja con el color del camion, para reconocer la hoja de un vistazo. */
        .franja { height: 5px; margin-top: 8px; border-radius: 2px }

        /* Resumen en tarjetas. */
        .resumen { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 8px -5px 0 }
        .resumen td { border: 1px solid #ddd; border-radius: 3px; padding: 5px 7px; width: 25% }
        .resumen .et { font-size: 7.5px; color: #777; text-transform: uppercase; letter-spacing: .4px }
        .resumen .val { font-size: 15px; font-weight: bold; color: #37474f; margin-top: 1px }

        .detalle tr.grupo td { background: #eceff1; color: #37474f; font-weight: bold; font-size: 8.5px;
                               text-transform: uppercase; letter-spacing: .5px; padding: 4px;
                               border-bottom: 1px solid #cfd8dc }
        .detalle tr.subtotal td { font-size: 8px; color: #607d8b; padding: 2px 4px 5px; border-bottom: 0 }
        .unidad { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 7.5px;
                  font-weight: bold; background: #eceff1; color: #455a64 }
        .unidad-KG { background: #fff3e0; color: #e65100 }
        .unidad-CAJA { background: #e8eaf6; color: #283593 }
        .cantidad { font-size: 11px; font-weight: bold }
        .check { width: 11px; height: 11px; border: 1px solid #90a4ae; border-radius: 2px; margin: 0 auto }

        .final-unidades td { padding: 5px 8px; font-size: 10px; border-bottom: 1px solid #eee }
        .final-unidades .final td { background: #37474f; color: #fff; font-weight: bold; font-size: 11px; border: 0 }

        .firmas { width: 100%; margin-top: 46px; border-collapse: collapse }
        .firmas td { width: 33%; text-align: center; font-size: 8.5px; color: #666; padding: 0 12px }
        .firma-linea { border-top: 1px solid #999; margin-bottom: 3px }
    </style>
</head>
<body>

{!! $cabecera !!}

<div class="franja" style="background: {{ $color }}"></div>

<table class="resumen">
    <tr>
        <td><div class="et">Pedidos</div><div class="val">{{ $pedidos }}</div></td>
        <td><div class="et">Clientes</div><div class="val">{{ $clientes }}</div></td>
        <td><div class="et">Productos distintos</div><div class="val">{{ $productos->pluck('codigo')->unique()->count() }}</div></td>
        <td><div class="et">Monto pedido</div><div class="val">Bs {{ number_format($monto, 2) }}</div></td>
    </tr>
</table>

<table class="detalle">
    <thead>
    <tr>
        <th style="width:20px">N°</th>
        <th style="width:52px; text-align:left">Código</th>
        <th style="text-align:left">Producto</th>
        <th style="width:42px">Unidad</th>
        <th style="width:62px" class="r">Cantidad</th>
        <th style="width:42px">Pedidos</th>
        <th style="width:66px" class="r">Monto Bs</th>
        <th style="width:26px">Carg.</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($grupos as $grupo => $filas)
        <tr class="grupo"><td colspan="8">{{ $grupo }}</td></tr>
        @foreach ($filas as $prod)
            @php $nro++; @endphp
            <tr class="{{ $nro % 2 === 0 ? 'par' : '' }}">
                <td class="c gris">{{ $nro }}</td>
                <td class="cod">{{ $prod->codigo }}</td>
                <td>{{ $prod->nombre }}</td>
                <td class="c"><span class="unidad unidad-{{ $prod->unidad }}">{{ $prod->unidad }}</span></td>
                <td class="r cantidad">{{ $cant($prod->total) }}</td>
                <td class="c">{{ $prod->pedidos }}</td>
                <td class="r">{{ number_format($prod->monto, 2) }}</td>
                <td><div class="check"></div></td>
            </tr>
        @endforeach
        <tr class="subtotal">
            <td colspan="4" class="r">Subtotal {{ $grupo }}:
                {{ $filas->groupBy('unidad')->map(function ($f, $u) use ($cant) { return $cant($f->sum('total')) . ' ' . $u; })->implode(' · ') }}
            </td>
            <td colspan="3" class="r">Bs {{ number_format($filas->sum('monto'), 2) }}</td>
            <td></td>
        </tr>
    @empty
        <tr><td colspan="8" class="c gris" style="padding:18px">
            No hay pedidos enviados {{ $placa !== '' ? 'para el camión ' . $placa : '' }} en esta fecha
        </td></tr>
    @endforelse
    </tbody>
</table>

@if ($productos->count())
    <table style="width:100%; margin-top:10px; border-collapse:collapse">
        <tr>
            <td style="width:55%"></td>
            <td>
                <table class="final-unidades" style="width:100%; border-collapse:collapse">
                    @foreach ($porUnidad as $unidad => $suma)
                        <tr>
                            <td>Total en <span class="unidad unidad-{{ $unidad }}">{{ $unidad }}</span></td>
                            <td class="r"><b>{{ $cant($suma) }}</b></td>
                        </tr>
                    @endforeach
                    <tr class="final">
                        <td>MONTO TOTAL</td>
                        <td class="r">Bs {{ number_format($monto, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endif

<table class="firmas">
    <tr>
        <td><div class="firma-linea"></div>Entregado por (almacén)</td>
        <td><div class="firma-linea"></div>Recibido por (chofer)</td>
        <td><div class="firma-linea"></div>Revisado por</td>
    </tr>
</table>

<div class="pie">
    <div class="legal">
        {{ $empresa }} &middot; {{ $placa !== '' ? 'Carga del camión ' . $placa : 'Productos totales' }}
        del {{ date('d/m/Y', strtotime($fecha)) }} &middot; generado el {{ date('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
