<?php

namespace App\Http\Controllers;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

/**
 * Impresion de la factura fiscal (tbfactura) desde la pantalla de Ventas.
 *
 * Es el mismo modelo que el portal publico de facturas (carpeta modeloFactura/):
 * mismo HTML, mismo QR al SIAT, misma disposicion. Solo se imprime lo que el
 * SIN emitio de verdad, para que el documento tenga credito fiscal.
 */
class FacturaFiscalController extends Controller
{
    /**
     * Representacion grafica de la factura, identica a la del portal publico.
     */
    public function pdf($codAut)
    {
        $fact = DB::table('tbfactura')->where('CodAut', $codAut)->first();
        if (!$fact) {
            return response()->json(['message' => 'La factura no existe'], 404);
        }

        $cliente = DB::table('tbclientes')->whereRaw('TRIM(Id) = ?', [trim($fact->IdCli)])->first();
        if (!$cliente) {
            return response()->json(['message' => 'No se encontro el cliente de la factura'], 404);
        }

        $detalle = DB::select("SELECT v.PVentUnit,v.Monto,v.cant,v.cod_pro,v.Descuatot,p.Producto,(select m.Descripcion from tbunidmed m
        where m.Codmed=p.codUnid) as unidad
        FROM tbventas v inner join tbproductos p on v.cod_pro=p.cod_prod
        WHERE v.Comanda=?", [$fact->comanda]);

        if (sizeof($detalle) <= 0) {
            return response()->json(['message' => 'La factura no tiene detalle de productos'], 404);
        }

        $ley = DB::table('tbdatfac1')->where('Docifi', $fact->Docifi)->first();

        return $this->armarPdf($fact, $cliente, $detalle, $ley ? $ley->Leyenda : '');
    }

    /** Arma el PDF; es el mismo HTML del portal publico de facturas. */
    private function armarPdf($fact, $cliente, $detalle, $leyenda)
    {
        $subtotal = 0;
        $contenido = '';
        foreach ($detalle as $value) {
            if ($value->unidad == null) $value->unidad = 'KILOGRAMO';
            $contenido .= "<tr ><td class='detalle2' style='text-align:right;'>" . $value->cod_pro . "</td><td class='detalle2' style='text-align:right;'>" . number_format($value->cant, 2) . "</td><td class='detalle2'>" . $value->unidad . "</td><td class='detalle2'>" . $value->Producto . "</td><td class='detalle2' style='text-align:right;'>" . number_format($value->PVentUnit, 2) . "</td><td class='detalle2' style='text-align:right;'>" . number_format($value->Descuatot, 2) . "</td><td class='detalle2' style='text-align:right;'>" . number_format($value->Monto, 2) . "</td></tr>";
            $subtotal += $value->Monto;
        }

        $entero = intval($subtotal);
        $decimal = intval(($subtotal - $entero) * 100);
        $formatter = new NumeroALetras();

        $logo = base64_encode(file_get_contents(public_path('img/sofia.png')));

        $autoriza = $fact->cuffac;
        $png = base64_encode($this->qrPng($this->urlSiat($autoriza, $fact->nrofac)));

        $cadena = "<style>
        .imagen{
            width:150px;
        }
        *{
            margin:5px;
            font-size:12px;
            font-family: Calibri, sans-serif;
        }
        table {
            width: 100%;
          }
        .area{
            border: 1px solid;
            border-radius: 5px;
        }
        .titulo1{
            text-align: center;
            font-weight: bold;
        }
        .detalle  {
            border: 1px solid;
            border-collapse: collapse;
          }
          .detalle2 {
            padding: 2px;
            border: 1px solid;
            border-collapse: collapse;
            font-size: 11px;
          }
        </style>
        <div style='padding-left: 10px;padding-right: 10px'>
         <table>
        <tr>
        <td style='text-align: center;'>
            <img class='imagen' src='data:image/png;base64,$logo'>
        </td>
        <td>
        <table class='area'>
        <tr>
        <td><b>NIT:</b></td><td>" . config('siat.nit') . "</td></tr><tr><td><b>FACTURA No: </b></td><td>$fact->nrofac</td></tr><tr><td style='vertical-align:top'><b>COD. AUTORIZACION:</b> </td><td>" . substr($autoriza, 0, 23) . "<br>" . substr($autoriza, 23, 23) . "<br> " . substr($autoriza, 46) . "</td></tr></table></td></tr>
        <tr class='titulo1'><td class='area'>ALMACEN SOFIA<br>SUCURSAL 1<br>PUNTO DE VENTA $fact->PuntVenta<br>Prolongacion Campo Jordan esq Tacna Nro 28 ZONA Norte<br>Telefono : 5230064<br>ORURO</td><td><span style='color:blue;  font-size:16px;font-weight: bold'></span><br>$fact->comanda</td></tr></table>
        <div class='titulo1'><span style='color:blue; font-size:16px;font-weight: bold'>FACTURA</span><br><span>(Con derecho a crédito fiscal)</span></div>
        <table class='area'>
        <tr><td><b>FECHA:</b></td><td>$fact->FechaFac</td><td><b>NIT/CI/CEX:</b></td><td>$cliente->Id</td><td><b>Compl:</b></td><td>$cliente->complto</td></tr>
        <tr><td><b>Nombres/Razon Social:</b></td><td>$cliente->Nombres</td><td><b>Cod Cliente:</b></td><td>$cliente->Cod_Aut</td><td></td><td></td></tr>
        </table>
        <table class='detalle'>
        <tr>
            <th style='padding: 5px;border: 1px solid'>Código Producto Servicio</th>
            <th style='padding: 5px;border: 1px solid'>Cantidad</th>
            <th style='padding: 5px;border: 1px solid'>Unidad de Medida</th>
            <th style='padding: 5px;border: 1px solid'>Descripcion</th>
            <th style='padding: 5px;border: 1px solid'>Precio unitario</th>
            <th style='padding: 5px;border: 1px solid'>Descuento</th>
            <th style='padding: 5px;border: 1px solid'>Importe</th>
          </tr>
        " . $contenido . "
        </table>
        <table>
            <tr>
                <td style='vertical-align:top'><b>Son:</b> " . $formatter->toString($entero) . " $decimal/100 Bolivianos</td>
                <td><table class='detalle2'><tr class='detalle2'><td>SUBTOTAL Bs.</td><td style='color:blue; font-size:16px;font-weight: bold'>" . number_format($subtotal, 2) . "</td></tr><tr class='detalle2'><td>DESCUENTO Bs.</td><td>0</td></tr><tr class='detalle2'><td>TOTAL Bs.</td><td>" . number_format($subtotal, 2) . "</td></tr><tr class='detalle2'><td>MONTO GIFT CARD Bs.</td><td>0</td></tr><tr class='detalle2'><td><b>MONTO A PAGAR Bs.</b></td><td>" . number_format($subtotal, 2) . "</td></tr><tr class='detalle2'><td>IMPORTE BASE CRÉDITO FISCAL Bs.</td><td>" . number_format($subtotal, 2) . "</td></tr></table></td>
        </tr></table>
        <table><tr>
        <td style='text-align:center;'>&quot;ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY&quot;.</td>
        <td rowspan=3 style='width:30%; text-align:center;'><img src='data:image/png;base64," . $png . "' style='border:2px solid white;width: 120px;height: 120px'></td></tr>
        <tr><td style='text-align:center;'>$leyenda</td></tr>
        <tr><td style='text-align:center;'> Este documento es la Representación Gráfica de un Documento Fiscal Digital emitido en una modalidad de facturación en linea.</td></tr>
        </table>
        </div>

        ";

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($cadena);
        return $pdf->stream("factura_{$fact->nrofac}.pdf", ['Attachment' => false]);
    }

    /**
     * URL de verificacion del SIAT; es la misma que codifica el QR.
     * Se arma desde config/siat.php para poder apuntar a piloto por .env.
     */
    public static function urlSiat($cuf, $nrofac)
    {
        return config('siat.url_consulta')
            . '?nit=' . config('siat.nit')
            . '&cuf=' . $cuf
            . '&numero=' . $nrofac
            . '&t=2';
    }

    /**
     * QR en PNG dibujado con GD.
     *
     * El portal publico usa simple-qrcode, que para PNG necesita la extension
     * imagick; aca solo hay gd, asi que se toma la matriz de bacon/bacon-qr-code
     * y se pinta a mano. El contenido codificado es identico.
     */
    private function qrPng($texto, $objetivo = 250, $margen = 4)
    {
        $matriz = Encoder::encode(
            $texto,
            ErrorCorrectionLevel::L(),
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            null,
            false // sin prefijo ECI: algunos lectores no lo interpretan
        )->getMatrix();

        $modulos = $matriz->getWidth() + 2 * $margen;
        $escala = (int) max(1, floor($objetivo / $modulos));
        $lado = $modulos * $escala;

        $img = imagecreatetruecolor($lado, $lado);
        $blanco = imagecolorallocate($img, 255, 255, 255);
        $negro = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $lado, $lado, $blanco);

        for ($y = 0; $y < $matriz->getHeight(); $y++) {
            for ($x = 0; $x < $matriz->getWidth(); $x++) {
                if ($matriz->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $img,
                        ($x + $margen) * $escala,
                        ($y + $margen) * $escala,
                        ($x + $margen + 1) * $escala - 1,
                        ($y + $margen + 1) * $escala - 1,
                        $negro
                    );
                }
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }
}
