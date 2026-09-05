<?php

namespace App\Http\Controllers\Concerns;

/**
 * El formato de papel de la casa: el mismo que sale en la boleta de entrega y
 * en la factura, para que todo lo que se imprime del sistema se vea igual.
 *
 * Vive aparte porque lo usan la pantalla de facturacion y las hojas del recojo
 * del caminero, y lo que se firma no puede verse distinto segun de donde salio.
 */
trait PapeleriaSofia
{
    /** Los estilos comunes a todo lo que se imprime en tamano carta. */
    private function estilosImpresion()
    {
        return "
            @page { margin: 12mm 11mm 20mm 11mm }
            * { font-family: 'DejaVu Sans', sans-serif }
            body { font-size: 9.5px; color: #222 }
            .c { text-align: center } .r { text-align: right }
            .gris { color: #777 }

            /* Cabecera: logo, datos del emisor y caja del documento. */
            .cabecera { width: 100%; border-collapse: collapse }
            .cabecera td { vertical-align: top; padding: 0 }
            .logo { width: 118px }
            .empresa { font-size: 14px; font-weight: bold; color: #c1272d; letter-spacing: .5px }
            .empresa-dato { font-size: 8.5px; color: #555; line-height: 1.45 }

            .caja-doc { border: 1.5px solid #c1272d; border-radius: 3px; width: 100% }
            .caja-doc .tit { background: #c1272d; color: #fff; font-size: 10px;
                             font-weight: bold; text-align: center; padding: 3px; letter-spacing: 1px }
            .caja-doc td { padding: 2px 6px; font-size: 9px }
            .caja-doc .et { color: #666 }
            .caja-doc .nro { font-size: 15px; font-weight: bold; color: #c1272d }

            /* Datos del cliente. */
            .datos { width: 100%; border-collapse: collapse; margin-top: 8px;
                     border: 1px solid #ccc; border-radius: 3px }
            .datos td { padding: 3.5px 6px; border-bottom: 1px solid #eee; font-size: 9px }
            .datos .et { color: #777; font-size: 8px; text-transform: uppercase; letter-spacing: .3px }

            /* Detalle. */
            .detalle { width: 100%; border-collapse: collapse; margin-top: 9px }
            .detalle th { background: #37474f; color: #fff; font-size: 8px; font-weight: bold;
                          padding: 5px 4px; text-transform: uppercase; letter-spacing: .4px }
            .detalle td { padding: 4px; border-bottom: 1px solid #e4e4e4; font-size: 9px }
            .detalle tr.par td { background: #fafafa }
            .detalle .cod { color: #666; font-size: 8.5px }

            /* Totales. */
            .totales { width: 100%; border-collapse: collapse }
            .totales td { padding: 3.5px 8px; font-size: 9.5px; border-bottom: 1px solid #eee }
            .totales .final td { background: #37474f; color: #fff; font-size: 12px;
                                 font-weight: bold; border: 0 }
            .literal { border: 1px solid #ddd; padding: 6px 8px; font-size: 9px; line-height: 1.5 }
            .literal b { color: #555 }

            .aviso { border: 1.5px solid #c62828; background: #ffebee; color: #c62828;
                     font-weight: bold; text-align: center; padding: 5px; margin: 7px 0; font-size: 9.5px }

            .copia { text-align: center; font-size: 10px; font-weight: bold;
                     letter-spacing: 4px; color: #999; margin-top: 6px }
            .pie { position: fixed; bottom: -14mm; left: 0; right: 0 }
            .legal { font-size: 7.5px; color: #888; text-align: center; line-height: 1.5 }
        ";
    }

    /** Logo, datos de la empresa y, a la derecha, la caja del documento. */
    private function cabeceraEmisor($cajaDerecha)
    {
        $emisor = config('siat.emisor');
        $logo = is_file(public_path('img/sofia.png'))
            ? base64_encode(file_get_contents(public_path('img/sofia.png')))
            : '';

        return "<table class='cabecera'>
            <tr>
                <td style='width:130px'>"
                    . ($logo ? "<img class='logo' src='data:image/png;base64,$logo'>" : '')
                . "</td>
                <td style='padding-left:6px'>
                    <div class='empresa'>" . e($emisor['nombre']) . "</div>
                    <div class='empresa-dato'>
                        " . e($emisor['sucursal']) . " &middot; NIT " . e(config('siat.nit')) . "<br>
                        " . e($emisor['direccion']) . "<br>
                        Telf. " . e($emisor['telefono']) . " &middot; " . e($emisor['ciudad']) . "
                    </div>
                </td>
                <td style='width:210px'>$cajaDerecha</td>
            </tr>
        </table>";
    }
}
