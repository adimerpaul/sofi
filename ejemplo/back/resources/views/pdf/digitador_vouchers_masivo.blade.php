<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Boletas de Entrega</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 10px; font-size: 10px; color: #000; }
    .page { page-break-after: always; border: 1px solid #444; padding: 6px; min-height: 96%; }
    .page:last-child { page-break-after: auto; }
    .title-inline { display: table; width: 100%; margin-bottom: 4px; }
    .title-inline > div { display: table-cell; vertical-align: middle; }
    .title-left { width: 22%; font-weight: bold; font-size: 11px; }
    .title-center { width: 56%; text-align: center; font-size: 13px; letter-spacing: 4px; font-weight: bold; }
    .title-right { width: 22%; text-align: right; font-weight: bold; font-size: 11px; letter-spacing: 2px; }
    table { width: 100%; border-collapse: collapse; }
    .meta td, .detalle td, .detalle th, .totales td { border: 1px solid #222; padding: 2px 3px; }
    .meta td { font-size: 10px; }
    .detalle th { text-align: center; font-size: 10px; }
    .detalle td { font-size: 10px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .firma { margin-top: 24px; }
    .firma-linea { display: inline-block; min-width: 180px; border-top: 1px solid #111; margin: 0 8px; height: 10px; }
    .nota { margin-top: 8px; text-align: center; font-size: 10px; letter-spacing: .2px; }
    .logo-mini { width: 90px; height: auto; }
  </style>
</head>
<body>
@foreach($ventas as $venta)
  @php
    $cliente = $venta->cliente ?: $venta->pedido?->cliente;
    $vendedor = $venta->pedido?->user?->name ?: $venta->user?->name ?: 'No asignado';
    $zona = $cliente?->territorio ?: '-';
    $fechaEmision = trim(($venta->fecha ?? '') . ' ' . ($venta->hora ?? ''));
    $entrega = $venta->fecha ?? '-';
    $detalles = $venta->ventaDetalles;
    $subTotal = (float) $detalles->sum(fn($d) => (float)$d->cantidad * (float)$d->precio);
    $literal = app(\Luecano\NumeroALetras\NumeroALetras::class)->toWords((int)$subTotal);
    $decimal = (int) round(($subTotal - floor($subTotal)) * 100);
    $logoPath = public_path('images/sofia.png');
    $logoB64 = null;
    if (is_file($logoPath)) {
      $logoB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
  @endphp
  <div class="page">
    <div class="title-inline">
      <div class="title-left">
        @if($logoB64)
          <img src="{{ $logoB64 }}" class="logo-mini" alt="Logo">
        @else
          ALMACEN SOFIA
        @endif
      </div>
      <div class="title-center">BOLETA DE ENTREGA</div>
      <div class="title-right">ORIGINAL</div>
    </div>

    <table class="meta">
      <tr>
        <td style="width: 26%;"><b>CI/NIT:</b> {{ $cliente?->ci ?: '-' }}</td>
        <td style="width: 16%;"><b>TELF.:</b> {{ $cliente?->telefono ?: '-' }}</td>
        <td style="width: 25%;"><b>Lat.:</b> -</td>
        <td style="width: 16%;"><b>Zona:</b> {{ $zona }}</td>
        <td style="width: 17%;"><b>F. Impresión:</b> {{ now()->format('d/m/Y') }}</td>
      </tr>
      <tr>
        <td colspan="3"><b>Cliente:</b> {{ $cliente?->nombre ?: '-' }}</td>
        <td colspan="2"><b>F. Emisión:</b> {{ $fechaEmision }}</td>
      </tr>
      <tr>
        <td colspan="3"><b>Dirección:</b> {{ $cliente?->direccion ?: '-' }}</td>
        <td colspan="2"><b>F. Mov.:</b> {{ $entrega }}</td>
      </tr>
      <tr>
        <td colspan="3"><b>Vendedor:</b> {{ $vendedor }}</td>
        <td colspan="2"><b>Nro Pedido:</b> {{ $venta->pedido_id ?: '-' }}</td>
      </tr>
    </table>

    <table class="detalle" style="margin-top: 5px;">
      <thead>
      <tr>
        <th style="width: 8%;">CANT</th>
        <th style="width: 10%;">CODIGO</th>
        <th style="width: 35%;">CONCEPTO</th>
        <th style="width: 6%;">UNID</th>
        <th style="width: 8%;">P. BRUTO</th>
        <th style="width: 5%;">CJS</th>
        <th style="width: 5%;">KG</th>
        <th style="width: 7%;">P. NETO</th>
        <th style="width: 7%;">P. UNIT</th>
        <th style="width: 9%;">TOTAL</th>
      </tr>
      </thead>
      <tbody>
      @foreach($detalles as $d)
        @php
          $cantidad = (float) $d->cantidad;
          $precio = (float) $d->precio;
          $total = $cantidad * $precio;
          $codigo = $d->producto?->codigo ?: $d->producto_id;
          $concepto = mb_strtoupper((string)($d->nombre ?: ($d->producto?->nombre ?: '-')));
        @endphp
        <tr>
          <td class="right">{{ number_format($cantidad, 2) }}</td>
          <td class="center">{{ $codigo }}</td>
          <td>{{ $concepto }}</td>
          <td class="center">U</td>
          <td class="center">-</td>
          <td class="center">-</td>
          <td class="center">-</td>
          <td class="right">{{ number_format($cantidad, 2) }}</td>
          <td class="right">{{ number_format($precio, 2) }}</td>
          <td class="right">{{ number_format($total, 2) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <table class="totales" style="margin-top: 5px;">
      <tr>
        <td style="width: 66%;"><b>LITERAL :</b> {{ trim($literal) }} {{ str_pad((string) $decimal, 2, '0', STR_PAD_LEFT) }}/100</td>
        <td style="width: 34%; padding: 0;">
          <table style="width:100%; border-collapse: collapse;">
            <tr><td style="border:none; padding:2px 4px;"><b>SUB. TOT Bs.:</b></td><td class="right" style="border:none; padding:2px 4px;">{{ number_format($subTotal, 2) }}</td></tr>
            <tr><td style="border:none; padding:2px 4px;"><b>DESCT. Bs.:</b></td><td class="right" style="border:none; padding:2px 4px;">0.00</td></tr>
            <tr><td style="border:none; padding:2px 4px;"><b>TOTAL Bs.:</b></td><td class="right" style="border:none; padding:2px 4px;"><b>{{ number_format($subTotal, 2) }}</b></td></tr>
          </table>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 3px;"><b>PLACA Y DESTINO:</b> - &nbsp;&nbsp;&nbsp; <b>TIPO DE PAGO:</b> {{ mb_strtoupper((string)($venta->tipo_pago ?? '-')) }}</td>
      </tr>
      <tr>
        <td colspan="2" style="padding: 3px;"><b>OBS.:</b> {{ $venta->pedido?->observaciones ?: '-' }}</td>
      </tr>
    </table>

    <div class="firma">
      CI: <span class="firma-linea"></span><br><br>
      Nombre: <span class="firma-linea"></span><br><br>
      Firma <span class="firma-linea"></span>
    </div>
    <div class="nota">"RESPALDE SU CANCELACION DEL PRESENTE, CON EL SELLO Y O RECIBO DE COBRANZA"</div>
  </div>
@endforeach
</body>
</html>

