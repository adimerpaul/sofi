<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Facturas Emitidas</title>
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
    body { margin: 8px; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .row { width: 100%; }
    .box { border: 1px solid #111; border-radius: 4px; padding: 6px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .blue { color: #0d47a1; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px; }
    th { background: #f1f5f9; }
    .no-border td { border: none; padding: 2px; }
    .qr { width: 120px; height: 120px; }
    .logo { width: 130px; }
  </style>
</head>
<body>
@foreach($items as $it)
  @php
    /** @var \App\Models\Venta $venta */
    $venta = $it['venta'];
    $cliente = $venta->cliente ?: $venta->pedido?->cliente;
    $logoPath = public_path('images/sofia.png');
    $logoB64 = null;
    if (is_file($logoPath)) {
      $logoB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }
  @endphp
  <div class="page">
    <table class="no-border">
      <tr>
        <td style="width: 24%;" class="center">
          @if($logoB64)
            <img src="{{ $logoB64 }}" class="logo" alt="Logo">
          @else
            <div class="blue">{{ $razon }}</div>
          @endif
        </td>
        <td style="width: 48%;">
          <div class="box">
            <div><b>NIT:</b> {{ $nit }}</div>
            <div><b>FACTURA No:</b> {{ $venta->id }}</div>
            <div><b>COD. AUTORIZACION:</b><br>{{ $venta->cuf }}</div>
          </div>
        </td>
        <td style="width: 28%;" class="center">
          <div class="box">
            <div class="blue" style="font-size: 14px;">FACTURA</div>
            <div>(Con derecho a crédito fiscal)</div>
            <div style="margin-top: 4px;"><b>Comanda:</b> {{ $venta->pedido_id }}</div>
          </div>
        </td>
      </tr>
    </table>

    <div class="center box" style="margin-top: 6px;">
      <b>{{ $razon }}</b><br>
      {{ $direccion }}<br>
      Teléfono: {{ $telefono }}
    </div>

    <table style="margin-top: 6px;">
      <tr>
        <td><b>FECHA:</b> {{ $venta->fecha }} {{ $venta->hora }}</td>
        <td><b>NIT/CI/CEX:</b> {{ $cliente?->ci ?? '-' }}</td>
        <td><b>Compl:</b> {{ $cliente?->complemento ?? '-' }}</td>
      </tr>
      <tr>
        <td colspan="2"><b>Nombres/Razón Social:</b> {{ $cliente?->nombre ?? '-' }}</td>
        <td><b>Cod Cliente:</b> {{ $cliente?->id ?? '-' }}</td>
      </tr>
    </table>

    <table style="margin-top: 6px;">
      <thead>
      <tr>
        <th>Código</th>
        <th>Cantidad</th>
        <th>Unidad</th>
        <th>Descripción</th>
        <th>P. Unit</th>
        <th>Desc.</th>
        <th>Importe</th>
      </tr>
      </thead>
      <tbody>
      @foreach($it['detalles'] as $d)
        <tr>
          <td class="right">{{ $d->producto?->codigo ?? $d->producto_id }}</td>
          <td class="right">{{ number_format((float)$d->cantidad, 2) }}</td>
          <td>KILOGRAMO</td>
          <td>{{ $d->nombre ?: ($d->producto?->nombre ?? '-') }}</td>
          <td class="right">{{ number_format((float)$d->precio, 2) }}</td>
          <td class="right">0.00</td>
          <td class="right">{{ number_format((float)$d->cantidad * (float)$d->precio, 2) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>

    <table class="no-border" style="margin-top: 6px;">
      <tr>
        <td style="width: 65%; vertical-align: top;">
          <b>Son:</b> {{ $it['literal'] }}<br>
        </td>
        <td style="width: 35%;">
          <table>
            <tr><td>SUBTOTAL Bs.</td><td class="right">{{ number_format($it['monto'], 2) }}</td></tr>
            <tr><td>DESCUENTO Bs.</td><td class="right">0.00</td></tr>
            <tr><td><b>TOTAL Bs.</b></td><td class="right"><b>{{ number_format($it['monto'], 2) }}</b></td></tr>
            <tr><td>BASE CRÉDITO FISCAL Bs.</td><td class="right">{{ number_format($it['monto'], 2) }}</td></tr>
          </table>
        </td>
      </tr>
    </table>

    <table class="no-border" style="margin-top: 6px;">
      <tr>
        <td class="center" style="width: 70%;">
          "ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY".<br>
          {{ $venta->leyenda ?: 'Documento Fiscal Digital emitido en modalidad de facturación en línea.' }}
        </td>
        <td class="center" style="width: 30%;">
          <img src="data:image/svg+xml;base64,{{ $it['qr_svg'] }}" class="qr" alt="QR"><br>
        </td>
      </tr>
    </table>
  </div>
@endforeach
</body>
</html>
