<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExcelController extends Controller
{
    /** Codigos de pollo que van a la hoja de preparacion. */
    const POLLO_PREPARACION = [
        '500106', '500107', '500108', '500109', '501600', '501601',
        '501604', '501606', '501704', '502102', '502108', '502106', '502109',
        '502101', '501118','501116', '501117', '501114', '501115', '501119',
        // Pollo brasa y brasa con cogote.
        '501005', '501006', '501007', '501008', '501009',
        '511116', '511117', '511118', '511119',
    ];

    /** Codigos de cerdo que van a la hoja de preparacion. */
    const CERDO_PREPARACION = [
        '503305', '503903', '100001', '100002', '100003', '100004',
        '330001', '503624', '503303', '503304',
        '503906', '503606', '503609', '503709', '503600', '503681',
        '503711', '503605', '503655', '503632', '503660',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

////        $spreadsheet = new Spreadsheet();
//        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ppollo.xlsx');
//        $sheet = $spreadsheet->getActiveSheet();
//
//
//        $sheet->setCellValue('A1', 'ID');
//        $sheet->setCellValue('B1', 'Name');
//        $sheet->setCellValue('C1', 'Name2');
//        $sheet->setCellValue('D1', 'Name3');
//        $sheet->setCellValue('E1', 'Type');
//
//// Write an .xlsx file
//        $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
//        $date = str_replace(".", "", $date);
//        $filename = "export_".$date.".xlsx";
//        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
//        try {
//            $writer = new Xlsx($spreadsheet);
//            $writer->save($filename);
//            $content = file_get_contents($filename);
//        } catch(Exception $e) {
//            exit($e->getMessage());
//        }
//
//        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
//        header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );
//
//        echo $content;  // this actually send the file content to the browser
//
//        unlink($filename);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha)
    {
//        return DB::select("
//        SELECT pe.Nombre1,pe.App1,pe.CodAut,
//        (
//        SELECT count(*)
//        FROM tbpedidos p2
//        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='POLLO'
//        ) as pollo,
//        (
//        SELECT count(*)
//        FROM tbpedidos p2
//        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='RES'
//        ) as res,
//        (
//        SELECT count(*)
//        FROM tbpedidos p2
//        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='CERDO'
//        ) as cerdo
//        FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc
//        WHERE date(fecha)='".$fecha."' AND tipo IN ('POLLO','RES','CERDO')
//        GROUP BY pe.Nombre1,pe.App1,pe.CodAut;");
        return DB::select("
        SELECT
            pe.Nombre1,
            pe.App1,
            pe.CodAut,
            SUM(CASE WHEN p.tipo = 'POLLO' THEN 1 ELSE 0 END) AS pollo,
            SUM(CASE WHEN p.tipo = 'RES' THEN 1 ELSE 0 END) AS res,
            SUM(CASE WHEN p.tipo = 'CERDO' THEN 1 ELSE 0 END) AS cerdo
        FROM tbpedidos p
        INNER JOIN personal pe ON pe.CodAut = p.CIfunc
        WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ?
          AND p.tipo IN ('POLLO', 'RES', 'CERDO')
        GROUP BY pe.Nombre1, pe.App1, pe.CodAut
    ", [$fecha]);
    }

    public function consulta($t, $f1, $f2, $codaut)
    {
        if ($t == 'p') {
            $persona = DB::table('personal')->where('CodAut', $codaut)->first();
            $query = DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where p.deleted_at IS NULL AND c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='POLLO' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t = '';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ppollo.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c = 4;
            $sheet->setCellValue('E2', trim($persona->Nombre1) . ' ' . trim($persona->App1));
            $sheet->setCellValue('AB2', $f1);
            $cliente2728 = DB::table('tbclientes')->where('Cod_Aut', '2728')->value('Nombres');
            $cliente3070 = DB::table('tbclientes')->where('Cod_Aut', '3070')->value('Nombres');
            foreach ($query as $r) {
                $clienteBonificacion = '';
                if ($r->bonificacionId) {
                    $clienteBonificacion = DB::table('tbclientes')->where('Cod_Aut', $r->bonificacionId)->value('Nombres');
                }
//                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B' . $c, $r->bonificacionId == null ? $r->Nombres : ($r->bonificacionId == '2728' ? $cliente2728 : ($r->bonificacionId == '3070' ? $cliente3070 : $clienteBonificacion)));
                $sheet->setCellValue('C' . $c, $r->cbrasa5);
                $sheet->setCellValue('D' . $c, $r->ubrasa5);
                $sheet->setCellValue('E' . $c, $r->cbrasa6);
                $sheet->setCellValue('F' . $c, $r->cubrasa6);
                $sheet->setCellValue('G' . $c, $r->c104);
                $sheet->setCellValue('H' . $c, $r->u104);
                $sheet->setCellValue('I' . $c, $r->c105);
                $sheet->setCellValue('J' . $c, $r->u105);
                $sheet->setCellValue('K' . $c, $r->c106);
                $sheet->setCellValue('L' . $c, $r->u106);
                $sheet->setCellValue('M' . $c, $r->c107);
                $sheet->setCellValue('N' . $c, $r->u107);
                $sheet->setCellValue('O' . $c, $r->c108);
                $sheet->setCellValue('P' . $c, $r->u108);
                $sheet->setCellValue('Q' . $c, $r->c109);
                $sheet->setCellValue('R' . $c, $r->u109);
                $sheet->setCellValue('S' . $c, $r->rango);
                $sheet->setCellValue('T' . $c, $r->ala == '' ? '' : $r->ala . '' . $r->unidala);
                $sheet->setCellValue('U' . $c, $r->cadera == '' ? '' : $r->cadera . '' . $r->unidcadera);
                $sheet->setCellValue('V' . $c, $r->pecho == '' ? '' : $r->pecho . '' . $r->unidpecho);
                $sheet->setCellValue('W' . $c, $r->pie == '' ? '' : $r->pie . '' . $r->unidpie);
                $sheet->setCellValue('X' . $c, $r->filete == '' ? '' : $r->filete . '' . $r->unidfilete);
                $sheet->setCellValue('Y' . $c, $r->cuello == '' ? '' : $r->cuello . '' . $r->unidcuello);
                $sheet->setCellValue('Z' . $c, $r->hueso == '' ? '' : $r->hueso . '' . $r->unidhueso);
                $sheet->setCellValue('AA' . $c, $r->menu == '' ? '' : $r->menu . '' . $r->unidmenu);
                $sheet->setCellValue('AB' . $c, $r->bs);
                $sheet->setCellValue('AC' . $c, $r->bs2);
                $sheet->setCellValue('AD' . $c, $r->pago == 'CONTADO' ? 'si' : 'no');
                $sheet->setCellValue('AE' . $c, $r->Observaciones);
                $sheet->setCellValue('AF' . $c, $r->fact);
                $sheet->setCellValue('AG' . $c, $r->horario);
//                $sheet->setCellValue('AH'.$c, $r->comentario." ".($r->bonificacionAprovacion?'Bonif.aprobada por: '.$r->bonificacionAprovacion.' Cliente: '.$clienteBonificacion:''));
                $sheet->setCellValue('AH' . $c, $r->bonificacionId == null ? $r->comentario : $r->Nombres . $r->comentario);
                $c++;
            }
//            return $t;
            //        $spreadsheet = new Spreadsheet();

//            $sheet->setCellValue('A1', 'ID');


// Write an .xlsx file
            $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1) . '_' . trim($persona->App1) . "_" . $date . ".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch (Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

//        return $t;


        if ($t == 'r') {
            $persona = DB::table('personal')->where('CodAut', $codaut)->first();
            $query = DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where p.deleted_at IS NULL AND c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='RES' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t = '';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('pres.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c = 6;
            $sheet->setCellValue('C3', trim($persona->Nombre1) . ' ' . trim($persona->App1));
            $sheet->setCellValue('J3', $f1);
            foreach ($query as $r) {
//                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B' . $c, $r->Nombres);
                $sheet->setCellValue('C' . $c, $r->pfrial);
                $sheet->setCellValue('D' . $c, $r->trozado);
                $sheet->setCellValue('E' . $c, $r->entero);
                $sheet->setCellValue('F' . $c, $r->pierna);
                $sheet->setCellValue('G' . $c, $r->brazo);
                $sheet->setCellValue('J' . $c, $r->Observaciones);
                $sheet->setCellValue('K' . $c, $r->pago == 'CONTADO' ? 'si' : 'no');
                $sheet->setCellValue('L' . $c, $r->fact);
                $sheet->setCellValue('M' . $c, $r->hoario);
                $sheet->setCellValue('N' . $c, $r->comentario);
                $c++;
            }
//            return $t;
            //        $spreadsheet = new Spreadsheet();

//            $sheet->setCellValue('A1', 'ID');


// Write an .xlsx file
            $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1) . '_' . trim($persona->App1) . "_res_" . $date . ".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch (Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

//        return $t;
        if ($t == 'c') {
            $persona = DB::table('personal')->where('CodAut', $codaut)->first();
            $query = DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where p.deleted_at IS NULL AND c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='CERDO' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t = '';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('pcerdo.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c = 6;
            $sheet->setCellValue('C3', trim($persona->Nombre1) . ' ' . trim($persona->App1));
            $sheet->setCellValue('J3', $f1);
            foreach ($query as $r) {
                $clienteBonificacion = '';
                if ($r->bonificacionId) {
                    $clienteBonificacion = DB::table('tbclientes')->where('Cod_Aut', $r->bonificacionId)->value('Nombres');
                }
                //                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B' . $c, $r->Nombres);
                $sheet->setCellValue('C' . $c, $r->pfrial);
                //$sheet->setCellValue('D'.$c, $r->trozado);
                $sheet->setCellValue('E' . $c, $r->entero);
                $sheet->setCellValue('F' . $c, $r->desmembre);
                $sheet->setCellValue('H' . $c, $r->corte);
                $sheet->setCellValue('I' . $c, $r->kilo);
                $sheet->setCellValue('J' . $c, $r->Observaciones);
                $sheet->setCellValue('U' . $c, $r->pago == 'CONTADO' ? 'si' : 'no');
                $sheet->setCellValue('V' . $c, $r->fact);
                $sheet->setCellValue('W' . $c, $r->horario);
                $sheet->setCellValue('X' . $c, $r->comentario . " " . ($r->bonificacionAprovacion ? 'Bonif.aprobada por: ' . $r->bonificacionAprovacion . ' Cliente: ' . $clienteBonificacion : ''));
                $c++;
            }
            //            return $t;
            //        $spreadsheet = new Spreadsheet();

            //            $sheet->setCellValue('A1', 'ID');


            // Write an .xlsx file
            $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1) . '_' . trim($persona->App1) . "_cerd_" . $date . ".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch (Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

    }

    public function reporteEmbutido(Request $request)
    {
        return DB::SELECT("
        SELECT
            c.Id,
            c.Nombres,c.zona,
            c.Direccion,
            c.Telf,
            c.CiVend,
            c.lu,
            c.Ma,
            c.Mi,
            c.Ju,
            c.Vi,
            c.Sa,
            c.do,
            p.NroPed,
            p.cod_prod,
            p.idCli,
            p.Cant,
            p.precio,
            p.fecha,
            p.Observaciones,
            p.subtotal,
            u.Producto,
            p.pago,
            p.fact,
            p.horario,
            p.comentario,
            p.estado,
            e.Nombre1,
            e.App1,
            e.Apm,
            CASE
                WHEN TRIM(COALESCE(c.CiVend, '')) <> '' AND TRIM(COALESCE(c.CiVend, '')) <> TRIM(COALESCE(e.ci, '')) THEN 'FUERA DE RUTA'
                WHEN DAYOFWEEK(p.fecha) = 1 AND IFNULL(c.do, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 2 AND IFNULL(c.lu, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 3 AND IFNULL(c.Ma, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 4 AND IFNULL(c.Mi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 5 AND IFNULL(c.Ju, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 6 AND IFNULL(c.Vi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 7 AND IFNULL(c.Sa, 0) = 1 THEN 'EN RUTA'
                ELSE 'FUERA DE RUTA'
            END AS estado_ruta
        FROM tbpedidos p
        INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
        INNER JOIN tbproductos u ON u.cod_prod = p.cod_prod
        INNER JOIN personal e ON p.CIfunc = e.CodAut
        WHERE p.deleted_at IS NULL AND p.tipo = 'NORMAL'
          AND DATE(p.fecha) >= '$request->ini'
          AND DATE(p.fecha) <= '$request->fin'
          AND p.CIfunc = '$request->codaut'");
    }

    public function reporteCerdo(Request $request)
    {
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c
        where p.deleted_at IS NULL AND c.Cod_Aut=p.idCli and date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and tipo='CERDO' AND CIfunc='$request->codaut' ");
    }

    public function reporteCerdoTodo(Request $request)
    {
        return DB::SELECT("SELECT * from tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  inner join personal e on p.CIfunc=e.CodAut
        where p.deleted_at IS NULL AND  date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and p.tipo='CERDO' ");
    }

    public function reporteEmbutidoTodo(Request $request)
    {
        return DB::SELECT("
        SELECT
            c.Id,
            c.Nombres,c.zona,
            c.Direccion,
            c.Telf,
            c.CiVend,
            c.lu,
            c.Ma,
            c.Mi,
            c.Ju,
            c.Vi,
            c.Sa,
            c.do,
            p.NroPed,
            p.cod_prod,
            p.idCli,
            p.Cant,
            p.precio,
            p.fecha,
            p.Observaciones,
            p.subtotal,
            u.Producto,
            p.pago,
            p.fact,
            e.Nombre1,
            e.App1,
            e.Apm,
            p.horario,
            p.comentario,
            p.estado,
            CASE
                WHEN TRIM(COALESCE(c.CiVend, '')) <> '' AND TRIM(COALESCE(c.CiVend, '')) <> TRIM(COALESCE(e.ci, '')) THEN 'FUERA DE RUTA'
                WHEN DAYOFWEEK(p.fecha) = 1 AND IFNULL(c.do, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 2 AND IFNULL(c.lu, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 3 AND IFNULL(c.Ma, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 4 AND IFNULL(c.Mi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 5 AND IFNULL(c.Ju, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 6 AND IFNULL(c.Vi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 7 AND IFNULL(c.Sa, 0) = 1 THEN 'EN RUTA'
                ELSE 'FUERA DE RUTA'
            END AS estado_ruta
        FROM tbpedidos p
        INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
        INNER JOIN tbproductos u ON u.cod_prod = p.cod_prod
        INNER JOIN personal e ON p.CIfunc = e.CodAut
        WHERE p.deleted_at IS NULL AND p.tipo = 'NORMAL'
          AND DATE(p.fecha) >= '$request->ini'
          AND DATE(p.fecha) <= '$request->fin'");
    }

    /**
     * Reporte de embutidos en Excel, listo para la oficina.
     *
     * Son dos miradas de los mismos dias: todo lo que cargaron los
     * preventistas —incluido lo que todavia no enviaron, que puede seguir
     * cambiando— y solo lo enviado, que es lo que de verdad se despacha. Por
     * eso el alcance viaja como parametro y queda escrito en el encabezado del
     * archivo: abierto suelto, el Excel dice cual de los dos es.
     *
     * Ademas del detalle van dos resumenes, por producto y por preventista,
     * que es lo que se mira antes de entrar linea por linea.
     */
    public function reporteEmbutidoExcel(Request $request)
    {
        $datos = $request->validate([
            'ini' => 'required|date_format:Y-m-d',
            'fin' => 'required|date_format:Y-m-d|after_or_equal:ini',
            'enviados' => 'nullable|boolean',
        ]);
        $soloEnviados = (bool) ($datos['enviados'] ?? false);

        $filas = $this->filasEmbutido($datos['ini'], $datos['fin'], $soloEnviados);

        if (!count($filas)) {
            return response()->json([
                'message' => $soloEnviados
                    ? 'No hay pedidos de embutidos enviados en esas fechas'
                    : 'No hay pedidos de embutidos en esas fechas',
            ], 422);
        }

        $rango = $datos['ini'] === $datos['fin']
            ? date('d/m/Y', strtotime($datos['ini']))
            : 'del ' . date('d/m/Y', strtotime($datos['ini'])) . ' al ' . date('d/m/Y', strtotime($datos['fin']));
        $alcance = $soloEnviados ? 'Solo pedidos enviados' : 'Todos los pedidos (enviados y sin enviar)';
        $subtitulo = $alcance . ' · ' . $rango . ' · ' . count($filas) . ' líneas · generado el ' . date('d/m/Y H:i');

        $libro = new Spreadsheet();

        $this->hojaReporte(
            $libro->getActiveSheet()->setTitle('Detalle'),
            'EMBUTIDOS · DETALLE', $subtitulo, $this->columnasEmbutido(), $filas
        );
        $this->hojaReporte(
            $libro->createSheet()->setTitle('Por producto'),
            'EMBUTIDOS · RESUMEN POR PRODUCTO', $subtitulo,
            [
                ['Código', 'cod_prod', 12, 'texto'],
                ['Producto', 'producto', 38, 'texto'],
                ['Pedidos', 'pedidos', 10, 'entero'],
                ['Cantidad', 'cantidad', 12, 'cantidad'],
                ['Importe Bs', 'importe', 14, 'importe'],
            ],
            $this->resumenEmbutido($filas, ['cod_prod', 'producto'], true)
        );
        $this->hojaReporte(
            $libro->createSheet()->setTitle('Por preventista'),
            'EMBUTIDOS · RESUMEN POR PREVENTISTA', $subtitulo,
            [
                ['Preventista', 'preventista', 30, 'texto'],
                ['Clientes', 'clientes', 10, 'entero'],
                ['Pedidos', 'pedidos', 10, 'entero'],
                ['Importe Bs', 'importe', 14, 'importe'],
            ],
            $this->resumenEmbutido($filas, ['preventista'], false)
        );

        $libro->setActiveSheetIndex(0);

        $writer = new Xlsx($libro);
        $nombre = 'embutidos_' . ($soloEnviados ? 'enviados' : 'todos') . '_' . $datos['ini']
            . ($datos['ini'] === $datos['fin'] ? '' : '_a_' . $datos['fin']) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Las lineas de embutidos del rango, ya armadas como salen en el Excel.
     *
     * Es la misma consulta de reporteEmbutidoTodo —incluido el calculo de si
     * el cliente estaba en la ruta del dia— con el filtro de lo enviado.
     */
    private function filasEmbutido($ini, $fin, $soloEnviados)
    {
        $sql = "
        SELECT
            p.fecha, p.NroPed, p.cod_prod, p.Cant, p.precio, p.Observaciones,
            p.pago, p.fact, p.horario, p.comentario, p.estado, p.idCli,
            c.Id, c.Nombres, c.zona,
            u.Producto,
            TRIM(CONCAT_WS(' ', NULLIF(TRIM(e.Nombre1), ''), NULLIF(TRIM(e.App1), ''), NULLIF(TRIM(e.Apm), ''))) AS preventista,
            CASE
                WHEN TRIM(COALESCE(c.CiVend, '')) <> '' AND TRIM(COALESCE(c.CiVend, '')) <> TRIM(COALESCE(e.ci, '')) THEN 'FUERA DE RUTA'
                WHEN DAYOFWEEK(p.fecha) = 1 AND IFNULL(c.do, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 2 AND IFNULL(c.lu, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 3 AND IFNULL(c.Ma, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 4 AND IFNULL(c.Mi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 5 AND IFNULL(c.Ju, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 6 AND IFNULL(c.Vi, 0) = 1 THEN 'EN RUTA'
                WHEN DAYOFWEEK(p.fecha) = 7 AND IFNULL(c.Sa, 0) = 1 THEN 'EN RUTA'
                ELSE 'FUERA DE RUTA'
            END AS estado_ruta
        FROM tbpedidos p
        INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
        INNER JOIN tbproductos u ON u.cod_prod = p.cod_prod
        INNER JOIN personal e ON p.CIfunc = e.CodAut
        WHERE p.deleted_at IS NULL AND p.tipo = 'NORMAL'
          AND DATE(p.fecha) >= ?
          AND DATE(p.fecha) <= ?
          " . ($soloEnviados ? "AND UPPER(TRIM(p.estado)) = 'ENVIADO'" : '') . "
        ORDER BY p.fecha, preventista, p.NroPed, u.Producto";

        return array_map(function ($r) {
            $cantidad = (float) $r->Cant;
            $precio = (float) $r->precio;

            return [
                'fecha' => date('d/m/Y', strtotime($r->fecha)),
                'pedido' => (string) $r->NroPed,
                'preventista' => $r->preventista ?: 'Sin preventista',
                'nit' => trim((string) $r->Id),
                'cliente' => trim((string) $r->Nombres),
                'zona' => trim((string) $r->zona),
                'ruta' => $r->estado_ruta,
                'cod_prod' => trim((string) $r->cod_prod),
                'producto' => trim((string) $r->Producto),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'importe' => round($cantidad * $precio, 2),
                'pago' => trim((string) $r->pago),
                'fact' => trim((string) $r->fact),
                'horario' => trim((string) $r->horario),
                'estado' => trim((string) $r->estado),
                'observaciones' => trim((string) $r->Observaciones),
                'comentario' => trim((string) $r->comentario),
                // No se imprimen: son para contar clientes y pedidos distintos.
                'cliente_id' => (int) $r->idCli,
                'nro_pedido' => (string) $r->NroPed,
            ];
        }, DB::select($sql, [$ini, $fin]));
    }

    /**
     * Agrupa las lineas del detalle para las hojas de resumen.
     *
     * Los pedidos y los clientes se cuentan distintos (un pedido con diez
     * productos es un pedido), y la cantidad solo se suma cuando el grupo es
     * un mismo producto: sumar kilos con unidades no dice nada.
     */
    private function resumenEmbutido(array $filas, array $claves, $sumarCantidad)
    {
        $grupos = [];

        foreach ($filas as $fila) {
            $partes = [];
            foreach ($claves as $k) {
                $partes[$k] = $fila[$k];
            }
            $clave = implode('|', $partes);

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = $partes + [
                    'pedidos' => [], 'clientes' => [], 'cantidad' => 0, 'importe' => 0,
                ];
            }

            $grupos[$clave]['pedidos'][$fila['nro_pedido']] = true;
            $grupos[$clave]['clientes'][$fila['cliente_id']] = true;
            $grupos[$clave]['cantidad'] += $fila['cantidad'];
            $grupos[$clave]['importe'] = round($grupos[$clave]['importe'] + $fila['importe'], 2);
        }

        $resumen = array_map(function ($grupo) use ($sumarCantidad) {
            $grupo['pedidos'] = count($grupo['pedidos']);
            $grupo['clientes'] = count($grupo['clientes']);
            if (!$sumarCantidad) {
                $grupo['cantidad'] = null;
            }
            return $grupo;
        }, array_values($grupos));

        // Lo que mas plata movio primero: es el orden en que se lee un resumen.
        usort($resumen, function ($a, $b) {
            return $b['importe'] == $a['importe'] ? 0 : ($b['importe'] < $a['importe'] ? -1 : 1);
        });

        return $resumen;
    }

    /** Columnas del detalle de embutidos: etiqueta, clave, ancho y formato. */
    private function columnasEmbutido()
    {
        return [
            ['Fecha', 'fecha', 11, 'texto'],
            ['Nº pedido', 'pedido', 11, 'texto'],
            ['Preventista', 'preventista', 26, 'texto'],
            ['CI / NIT', 'nit', 13, 'texto'],
            ['Cliente', 'cliente', 32, 'texto'],
            ['Zona', 'zona', 16, 'texto'],
            ['Ruta', 'ruta', 14, 'texto'],
            ['Código', 'cod_prod', 10, 'texto'],
            ['Producto', 'producto', 36, 'texto'],
            ['Cantidad', 'cantidad', 10, 'cantidad'],
            ['Precio Bs', 'precio', 11, 'importe'],
            ['Importe Bs', 'importe', 13, 'importe'],
            ['Pago', 'pago', 12, 'texto'],
            ['Factura', 'fact', 9, 'texto'],
            ['Horario', 'horario', 11, 'texto'],
            ['Estado', 'estado', 11, 'texto'],
            ['Observaciones', 'observaciones', 26, 'texto'],
            ['Comentario', 'comentario', 26, 'texto'],
        ];
    }

    /**
     * Arma una hoja con el formato del reporte.
     *
     * Titulo y alcance arriba, encabezado oscuro, importes con separador de
     * miles, fila de totales al pie y los encabezados fijos al desplazarse,
     * con filtro y con la cabecera repetida en cada hoja impresa.
     */
    private function hojaReporte($hoja, $titulo, $subtitulo, array $columnas, array $filas)
    {
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));

        $hoja->mergeCells('A1:' . $ultima . '1')->setCellValue('A1', $titulo);
        $hoja->mergeCells('A2:' . $ultima . '2')->setCellValue('A2', $subtitulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('616161');
        $hoja->getStyle('A1:' . $ultima . '2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($columnas as $i => $columna) {
            list($etiqueta, $clave, $ancho, $formato) = $columna;
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '4', $etiqueta);
            $hoja->getColumnDimension($letra)->setWidth($ancho);
        }

        $fila = 5;
        foreach ($filas as $registro) {
            foreach ($columnas as $i => $columna) {
                list($etiqueta, $clave, $ancho, $formato) = $columna;
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                // Los codigos y los numeros de pedido son etiquetas, no numeros:
                // sin esto Excel se come los ceros de adelante.
                if ($formato === 'texto') {
                    $hoja->setCellValueExplicit($letra . $fila, (string) $registro[$clave], DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue($letra . $fila, $registro[$clave]);
                }
            }
            $fila++;
        }

        $hoja->setCellValue('A' . $fila, 'TOTAL (' . count($filas) . ')');
        foreach ($columnas as $i => $columna) {
            list($etiqueta, $clave, $ancho, $formato) = $columna;
            // El precio no se suma: sumar precios unitarios no es ningun total.
            if ($formato !== 'importe' || $clave === 'precio') {
                continue;
            }
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . $fila, '=SUM(' . $letra . '5:' . $letra . ($fila - 1) . ')');
        }

        $hoja->getStyle('A4:' . $ultima . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '37474F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension(4)->setRowHeight(22);
        $hoja->getStyle('A' . $fila . ':' . $ultima . $fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECEFF1']],
        ]);
        $hoja->getStyle('A4:' . $ultima . $fila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B0BEC5');

        foreach ($columnas as $i => $columna) {
            list($etiqueta, $clave, $ancho, $formato) = $columna;
            if ($formato === 'texto') {
                continue;
            }
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->getStyle($letra . '5:' . $letra . $fila)->getNumberFormat()->setFormatCode(
                $formato === 'entero' ? '#,##0' : '#,##0.00'
            );
        }

        $hoja->freezePane('A5');
        $hoja->setAutoFilter('A4:' . $ultima . ($fila - 1));

        $impresion = $hoja->getPageSetup();
        $impresion->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $impresion->setFitToWidth(1);
        $impresion->setFitToHeight(0);
        $impresion->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $hoja->getHeaderFooter()->setOddFooter('&L' . $titulo . '&RPágina &P de &N');

        return $hoja;
    }

    public function reportePollo(Request $request)
    {
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c
        where p.deleted_at IS NULL AND c.Cod_Aut=p.idCli and date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and tipo='POLLO' AND CIfunc='$request->codaut' ");
    }

    public function listregistro(Request $request)
    {
        return DB::SELECT("SELECT pe.Nombre1,pe.App1,pe.CodAut
        FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc
        WHERE p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' AND date(p.fecha)<='$request->fin' GROUP BY pe.Nombre1,pe.App1,pe.CodAut;");
    }

    public function reportePollo2(Request $request)
    {
        return DB::SELECT("
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'ubrasa5' producto,p.ubrasa5 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.ubrasa5 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'cbrasa5' producto,p.cbrasa5 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cbrasa5 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'ubrasa6' producto,p.cubrasa6 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cubrasa6 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'cbrasa6' producto,p.cbrasa6 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cbrasa6 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u104' producto,p.u104 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u104 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c104' producto,p.c104 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c104 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u105' producto,p.u105 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u105 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c105' producto,p.c105 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c105 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u106' producto,p.u106 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u106 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c106' producto,p.c106 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c106 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u107' producto,p.u107 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u107 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c107' producto,p.c107 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c107 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u108' producto,p.u108 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u108 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c108' producto,p.c108 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c108 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'u109' producto,p.u109 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u109 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'c109' producto,p.c109 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c109 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'rango' producto,p.rango cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.rango is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'ala' producto,concat(p.ala,' ',p.unidala) cantidad ,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.ala is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'cadera' producto,concat(p.cadera,' ',p.unidcadera) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cadera is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'pecho' producto,concat(p.pecho,' ',p.unidpecho) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.pecho is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'pie' producto,concat(p.pie,' ',p.unidpie) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.pie is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'filete' producto,concat(p.filete,' ',p.unidfilete) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.filete is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'cuello' producto,concat(p.cuello,' ',p.unidcuello) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cuello is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'hueso' producto,concat(p.hueso,' ',p.unidhueso) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.hueso is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,c.zona,p.fecha,p.Observaciones,'menu' producto,concat(p.menu,' ',p.unidmenu) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario,p.bonificacionId,p.estado FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where p.deleted_at IS NULL AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.menu is NOT null

          ");

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function generarXlsPollo($fecha)
    {
        // Traer preventistas
        $preventistas = DB::select(
            "SELECT pe.Nombre1, pe.App1, pe.CodAut
         FROM personal pe
         INNER JOIN tbpedidos p ON pe.CodAut = p.CIfunc
         WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ? AND p.tipo = 'POLLO'
         GROUP BY pe.Nombre1, pe.App1, pe.CodAut",
            [$fecha]
        );

        // Cargar la plantilla
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('preparacion.xlsx');
        // Nombres definidos rotos (#REF! sin hoja) hacen fallar removeRow
        foreach ($spreadsheet->getDefinedNames() as $definedName) {
            if ($definedName->getWorksheet() === null) {
                $spreadsheet->removeDefinedName($definedName->getName(), $definedName->getScope());
            }
        }
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('G1', $fecha);

        // Mapa de colores: nombre -> HEX (sin #)
        $mapaColores = [
            'deep-orange-4' => 'FF7043', // NORTE
            'pink-4' => 'F06292', // BOLIVAR
            'blue-grey-4' => '37474F', // SE RECOGE
            'yellow' => 'F5EE17', // CENTRO
            'green-4' => '1B5E20', // APOYO
            'deep-purple-4' => '9575CD', // PROVINCIA
            'blue-4' => '0D47A1', // SUD
            'grey-6' => '757575', // SIN ZONA
        ];

        // Helper: determinar si un color de fondo es oscuro (para poner fuente blanca)
        $isDark = function (string $hex) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            return $luminance < 150;
        };

        $c = 4;
        // La observacion ya no va en una columna fija: se pega al final del
        // detalle de cada pedido, asi que el ancho de la hoja lo marca la fila
        // mas larga y hay que ir siguiendolo.
        $colMax = 6; // F: nombre del cliente

        foreach ($preventistas as $value) {
            // Nombre del preventista en columna F
            $sheet->setCellValue('F' . $c, trim($value->Nombre1) . ' ' . trim($value->App1));
            $c++;

            $pedidos = DB::select(
                "SELECT
                c.Nombres, p.fact, p.pago, p.bs, p.bs2,
                CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END AS campo_pago,
                cbrasa5, ubrasa5, cbrasa6, cubrasa6,
                c104, u104, c105, u105, c106, u106, c107, u107, c108, u108, c109, u109,
                rango, ala, unidala, cadera, unidcadera, pecho, unidpecho, pie, unidpie,
                filete, unidfilete, cuello, unidcuello, hueso, unidhueso, menu, unidmenu,
                Observaciones, horario, color, bonificacionId
             FROM tbpedidos p
             INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
             WHERE p.deleted_at IS NULL AND p.CIfunc = ? AND DATE(p.fecha) = ? AND p.tipo = 'POLLO' AND p.estado = 'ENVIADO'",
                [$value->CodAut, $fecha]
            );
            $cliente3070 = DB::table('tbclientes')->where('Cod_Aut', 3070)->value('Nombres');
            $cliente2728 = DB::table('tbclientes')->where('Cod_Aut', 2728)->value('Nombres');

            foreach ($pedidos as $r) {
                if ($r->horario == null) $r->horario = '';

                // Datos base de la fila
                $sheet->setCellValue('A' . $c, $r->horario);
                $sheet->setCellValue('B' . $c, $r->fact);
                $sheet->setCellValue('C' . $c, $r->campo_pago);
                $sheet->setCellValue('D' . $c, $r->bs2);
                $sheet->setCellValue('E' . $c, $r->bs);
                $sheet->setCellValue('F' . $c, $r->bonificacionId == null ?
                    $r->Nombres :
                    ($r->bonificacionId == 3070 ? $cliente3070 : ($r->bonificacionId == 2728 ? $cliente2728 : $r->Nombres)));

                // === Color SOLO en el nombre del cliente (F{fila}) ===
                if (!empty($r->color) && isset($mapaColores[$r->color])) {
                    $hex = $mapaColores[$r->color];
                    $celdaNombre = "F{$c}";

                    $sheet->getStyle($celdaNombre)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'color' => ['rgb' => $hex]
                        ]
                    ]);

                    // Contraste de la fuente para esa celda
                    $fontColor = $isDark($hex) ? 'FFFFFF' : '000000';
                    $sheet->getStyle($celdaNombre)->getFont()->getColor()->setRGB($fontColor);
                }

                // ===================== Productos por cliente ======================
                $productos = [];

                // B5
                if ($r->cbrasa5 != null || $r->ubrasa5 != null) {
                    $productos[] = ['B5', ($r->cbrasa5 != null) ? ($r->cbrasa5 . ' cja') : ($r->ubrasa5 . ' u')];
                } else {
                    $productos[] = ['B5', ''];
                }

                // B6
                if ($r->cbrasa6 != null || $r->cubrasa6 != null) {
                    $productos[] = ['B6', ($r->cbrasa6 != null) ? ($r->cbrasa6 . ' cja') : ($r->cubrasa6 . ' u')];
                } else {
                    $productos[] = ['B6', ''];
                }

                // 104
                if ($r->c104 != null || $r->u104 != null) {
                    $productos[] = ['104', ($r->c104 != null) ? ($r->c104 . ' cja') : ($r->u104 . ' u')];
                } else {
                    $productos[] = ['104', ''];
                }

                // 105
                if ($r->c105 != null || $r->u105 != null) {
                    $productos[] = ['105', ($r->c105 != null) ? ($r->c105 . ' cja') : ($r->u105 . ' u')];
                } else {
                    $productos[] = ['105', ''];
                }

                // 106
                if ($r->c106 != null || $r->u106 != null) {
                    $productos[] = ['106', ($r->c106 != null) ? ($r->c106 . ' cja') : ($r->u106 . ' u')];
                } else {
                    $productos[] = ['106', ''];
                }

                // 107
                if ($r->c107 != null || $r->u107 != null) {
                    $productos[] = ['107', ($r->c107 != null) ? ($r->c107 . ' cja') : ($r->u107 . ' u')];
                } else {
                    $productos[] = ['107', ''];
                }

                // 108
                if ($r->c108 != null || $r->u108 != null) {
                    $productos[] = ['108', ($r->c108 != null) ? ($r->c108 . ' cja') : ($r->u108 . ' u')];
                } else {
                    $productos[] = ['108', ''];
                }

                // 109
                if ($r->c109 != null || $r->u109 != null) {
                    $productos[] = ['109', ($r->c109 != null) ? ($r->c109 . ' cja') : ($r->u109 . ' u')];
                } else {
                    $productos[] = ['109', ''];
                }

                // Rango
                $productos[] = ['Rango', ($r->rango != null) ? ($r->rango . ' u') : ''];

                // Ala
                $productos[] = ['Ala', ($r->ala != null) ? ($r->ala . ' ' . (strtolower($r->unidala) ?? '')) : ''];

                // Cadera
                $productos[] = ['Cadera', ($r->cadera != null) ? ($r->cadera . ' ' . (strtolower($r->unidcadera) ?? '')) : ''];

                // Pecho
                $productos[] = ['Pecho', ($r->pecho != null) ? ($r->pecho . ' ' . (strtolower($r->unidpecho) ?? '')) : ''];

                // Pie
                $productos[] = ['p/m', ($r->pie != null) ? ($r->pie . ' ' . (strtolower($r->unidpie) ?? '')) : ''];

                // Filete
                $productos[] = ['Filete', ($r->filete != null) ? ($r->filete . ' ' . (strtolower($r->unidfilete) ?? '')) : ''];

                // Cuello
                $productos[] = ['Cuello', ($r->cuello != null) ? ($r->cuello . ' ' . (strtolower($r->unidcuello) ?? '')) : ''];

                // Hueso
                $productos[] = ['Hueso', ($r->hueso != null) ? ($r->hueso . ' ' . (strtolower($r->unidhueso) ?? '')) : ''];

                // Menú
                $productos[] = ['Menu', ($r->menu != null) ? ($r->menu . ' ' . (strtolower($r->unidmenu) ?? '')) : ''];

                // Inicia en columna G (índice 7)
                $col = 7;

                foreach ($productos as $prod) {
                    if ($prod[1] != '') {
                        $colLetter1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $colLetter2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);

                        $cell1 = $colLetter1 . $c;
                        $cell2 = $colLetter2 . $c;

                        $sheet->setCellValue($cell1, $prod[0]); // nombre
                        $sheet->setCellValue($cell2, $prod[1]); // cantidad

                        // Texto por defecto
                        $sheet->getStyle($cell1)->getFont()->setBold(false)->getColor()->setRGB('000000');
                        $sheet->getStyle($cell2)->getFont()->setBold(false)->getColor()->setRGB('000000');

                        // Si es "Rango": rojo y negrita
                        if ($prod[0] === 'Rango') {
                            $sheet->getStyle($cell1)->getFont()->setBold(true)->getColor()->setRGB('FF0000');
                            $sheet->getStyle($cell2)->getFont()->setBold(true)->getColor()->setRGB('FF0000');
                        }

                        $colMax = max($colMax, $col + 1);
                        $col += 4;
                    }
                }

                // Observaciones: despues del ultimo producto de cada pedido.
                //
                // En una bonificacion la columna F no lleva al cliente sino la
                // cuenta a la que se carga la baja ("BAJAS POR CALIDAD" o "BAJAS
                // POR BONIFICACIONES"), asi que el nombre real del cliente solo
                // aparece aca. Por eso se escribe aunque el pedido no traiga
                // observacion: sin esto preparacion no sabe a quien va.
                $observacion = trim((string) $r->Observaciones);
                if ($r->bonificacionId != null) {
                    $nombreCliente = trim((string) $r->Nombres);
                    $observacion = $observacion === ''
                        ? $nombreCliente
                        : $nombreCliente . ' - ' . $observacion;
                }

                if ($observacion !== '') {
                    $cell1 = Coordinate::stringFromColumnIndex($col) . $c;
                    $sheet->setCellValue($cell1, $observacion);
                    $sheet->getStyle($cell1)->applyFromArray([
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $colMax = max($colMax, $col);
                }

                $c++;
            }
        }

        $colFinal = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colMax);

        // Ancho de columnas por defecto (la plantilla trae anchos personalizados)
        foreach ($sheet->getColumnDimensions() as $colDim) {
            $colDim->setWidth(-1);
            $colDim->setAutoSize(false);
        }
        // FACTURA / CONTADO / P. TROZADO / P. POLLO comprimidas y CLIENTE ancha
        foreach (['B', 'C', 'D', 'E'] as $colComprimida) {
            $sheet->getColumnDimension($colComprimida)->setWidth(7);
        }
        $sheet->getColumnDimension('F')->setWidth(35);

        // Letra 12 y alto de fila por defecto en toda la grilla de datos
        $sheet->getStyle('A4:' . $colFinal . ($c + 1))->getFont()->setSize(12);
        for ($fila = 4; $fila <= $c + 1; $fila++) {
            $sheet->getRowDimension($fila)->setRowHeight(-1);
        }

        // Quitar las filas sobrantes de la plantilla (deja 2 filas libres al final)
        $ultimaFila = $sheet->getHighestRow();
        if ($ultimaFila > $c + 1) {
            $sheet->removeRow($c + 2, $ultimaFila - $c - 1);
            // removeRow no limpia los altos de fila; sin esto las filas vacias siguen ocupando espacio
            for ($fila = $c + 2; $fila <= $ultimaFila; $fila++) {
                $sheet->getRowDimension($fila)->setRowHeight(-1);
            }
        }
        $sheet->getPageSetup()->setPrintArea('A1:' . $colFinal . ($c + 1));

        // Vista normal (la plantilla venia en vista previa de salto de pagina)
        $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_NORMAL);
        $sheet->getSheetView()->setZoomScale(70);

        $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
        $date = str_replace(".", "", $date);
        $filename = "Frial_Pollo_" . $date . ".xlsx";

        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

        echo $content;
        @unlink($filename);
    }


    /**
     * Hoja de pesos de preparacion con los pedidos cargados por codigo.
     *
     * Desde el 2026-10-05 pollo, cerdo y res ya no se piden en las columnas
     * fijas de tbpedidos (c106, ala, pecho...) sino como una linea por
     * producto. Se saca una hoja por especie, sea cual sea el tipo del pedido:
     * - pollo: los codigos de POLLO_PREPARACION.
     * - cerdo: los codigos de CERDO_PREPARACION.
     * Van por lista y no por tbproductos.tipo porque ese tipo no coincide con
     * lo que pasa por preparacion (la bondiola figura NORMAL, por ejemplo).
     * Los comodines del formato viejo (501607 POLLO AGRANEL, 100005 CERDO
     * AGRANEL) no entran.
     *
     * Por cada pedido una fila con el cliente y debajo una fila por producto
     * (Prod | Nº Cja | Bruto | Neto): Bruto y Neto van vacios para llenarlos
     * en la balanza.
     */
    public function generarXlsPreparacion($fecha, $especie = 'pollo')
    {
        $especie = strtolower($especie) === 'cerdo' ? 'cerdo' : 'pollo';
        $codigos = $especie === 'cerdo' ? self::CERDO_PREPARACION : self::POLLO_PREPARACION;
        $filtro = 'TRIM(p.cod_prod) IN (' . implode(', ', array_fill(0, count($codigos), '?')) . ')';
        $lineas = DB::select(
            "SELECT p.NroPed, p.CIfunc, TRIM(p.cod_prod) AS cod_prod, p.Cant, UPPER(TRIM(p.caja)) AS caja,
                    p.Observaciones, p.fact, p.pago, p.bs, p.bs2, p.horario, p.color, p.bonificacionId,
                    TRIM(c.Nombres) AS cliente, TRIM(pr.Producto) AS producto, UPPER(TRIM(pr.tipo)) AS tipo_prod,
                    TRIM(pr.codUnid) AS unidad, p.precio AS precio_pedido,
                    pr.Precio, pr.Precio_Costo, pr.Precio3, pr.Precio4, pr.Precio5, pr.Precio6, pr.Precio7,
                    pr.Precio8, pr.Precio9, pr.Precio10, pr.Precio11, pr.Precio12, pr.Precio13,
                    TRIM(CONCAT_WS(' ', NULLIF(TRIM(pe.Nombre1), ''), NULLIF(TRIM(pe.App1), ''))) AS preventista,
                    COALESCE(TRIM(z.zona), 'SIN ZONA') AS zona
             FROM tbpedidos p
             INNER JOIN tbproductos pr ON pr.cod_prod = p.cod_prod
             INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
             LEFT JOIN personal pe ON pe.CodAut = p.CIfunc
             LEFT JOIN colores z ON z.color = p.color
             WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ? AND p.estado = 'ENVIADO'
               AND " . $filtro . "
               AND TRIM(p.cod_prod) NOT IN ('501607', '100005')
             ORDER BY COALESCE(z.id, 999), p.NroPed, p.codAut",
            array_merge([$fecha], $codigos)
        );

        // Zona -> pedidos -> lineas, respetando el orden de la consulta (el de
        // la tabla colores). La zona sale del color del pedido.
        $zonas = [];
        foreach ($lineas as $l) {
            $zonas[$l->zona][$l->NroPed][] = $l;
        }

        $mapaColores = [
            'deep-orange-4' => 'FF7043', // NORTE
            'pink-4' => 'F06292', // BOLIVAR
            'blue-grey-4' => '37474F', // SE RECOGE
            'yellow' => 'F5EE17', // CENTRO
            'green-4' => '1B5E20', // APOYO
            'deep-purple-4' => '9575CD', // PROVINCIA
            'blue-4' => '0D47A1', // SUD
            'grey-6' => '757575', // SIN ZONA
        ];
        $isDark = function (string $hex) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b < 150;
        };
        $cuentasBaja = DB::table('tbclientes')->whereIn('Cod_Aut', [3070, 2728])
            ->pluck('Nombres', 'Cod_Aut');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($especie === 'cerdo' ? 'Cerdo' : 'Pollo');

        // Cada pedido es una fila con el cliente y debajo sus productos, uno
        // por fila con el nombre completo en la columna F. Columnas fijas:
        // A-F pedido y cliente, G-L el producto (con el precio que eligio el preventista) y M la observacion.
        $colFin = 'M';
        $celeste = 'DDEBF7';

        $sheet->setCellValue('F1', 'FECHA: ' . $fecha);
        $sheet->setCellValue('G1', 'HOJA DE PESOS ' . strtoupper($especie));
        $sheet->mergeCells('G1:M1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('C62828');

        $sheet->fromArray(['HORARIO', 'FACTURA', 'CONTADO', 'P. TROZADO', 'P. POLLO', 'CLIENTE',
            'Prod', 'Nom', 'Nº Cja', 'Precio', 'Bruto', 'Neto', 'Obs.'], null, 'A2');
        $sheet->getStyle('A2:M2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:E2')->getAlignment()->setTextRotation(90);
        $sheet->getRowDimension(2)->setRowHeight(52);

        $cantidad = function ($l) {
            $n = rtrim(rtrim(number_format((float) $l->Cant, 2, '.', ''), '0'), '.');
            $unidad = $l->caja !== null && $l->caja !== '' ? $l->caja : strtoupper((string) $l->unidad);
            $sufijo = ['U' => 'u', 'UNIDA' => 'u', 'UNIDAD' => 'u', 'KG' => 'kg', 'CAJA' => 'cja'][$unidad] ?? strtolower($unidad);
            return trim($n . ' ' . $sufijo);
        };

        $c = 3;
        $celdasObs = [];
        $finPedido = [];
        $filasCliente = [];
        $nPedido = 0;
        foreach ($zonas as $zona => $pedidos) {
            // Fila de la zona en gris oscuro, para no confundirla con la fila
            // de cada cliente que va debajo.
            $sheet->setCellValue('F' . $c, 'ZONA: ' . $zona);
            $sheet->getStyle("A{$c}:M{$c}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '595959']],
            ]);
            $c++;

            foreach ($pedidos as $ls) {
                $r = $ls[0];
                // En una bonificacion la fila va a la cuenta de la baja y el
                // cliente real pasa a la observacion.
                $esBaja = $r->bonificacionId != null && isset($cuentasBaja[$r->bonificacionId]);
                $hex = !empty($r->color) && isset($mapaColores[$r->color]) ? $mapaColores[$r->color] : null;
                $desde = $c;

                // Primero una fila con el cliente y los datos del pedido...
                $sheet->setCellValue('A' . $c, (string) $r->horario);
                $sheet->setCellValue('B' . $c, $r->fact);
                $sheet->setCellValue('C' . $c, strtoupper(trim((string) $r->pago)) === 'CONTADO' ? 'SI' : 'NO');
                $sheet->setCellValue('D' . $c, $r->bs2);
                $sheet->setCellValue('E' . $c, $r->bs);
                $sheet->setCellValue('F' . $c, $esBaja ? trim($cuentasBaja[$r->bonificacionId]) : $r->cliente);
                $sheet->getStyle('F' . $c)->getFont()->setBold(true);
                $filasCliente[] = $c;
                // El cliente real de una bonificacion va como observacion del pedido.
                if ($esBaja) {
                    $sheet->setCellValue('M' . $c, $r->cliente);
                    $celdasObs[] = 'M' . $c;
                }
                if ($hex) {
                    $sheet->getStyle('F' . $c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($hex);
                    $sheet->getStyle('F' . $c)->getFont()->getColor()->setRGB($isDark($hex) ? 'FFFFFF' : '000000');
                }
                $c++;

                // ...y debajo una fila por cada producto que pidio.
                foreach ($ls as $l) {
                    $sheet->setCellValue('F' . $c, '    ' . $l->producto);
                    $sheet->setCellValueExplicit('G' . $c, $l->cod_prod, DataType::TYPE_STRING);
                    // Solo las 3 primeras letras: el codigo ya lo identifica.
                    $sheet->setCellValue('H' . $c, mb_substr($l->producto, 0, 3));
                    $sheet->setCellValue('I' . $c, $cantidad($l));
                    $sheet->setCellValue('J' . $c, $this->precioElegido($l));

                    $obs = trim((string) $l->Observaciones);
                    if ($obs !== '') {
                        $sheet->setCellValue('M' . $c, $obs);
                        $celdasObs[] = 'M' . $c;
                    }
                    $c++;
                }

                // Pedidos alternados en celeste para ver donde empieza cada uno.
                if ($nPedido++ % 2 === 1) {
                    $sheet->getStyle("G{$desde}:L" . ($c - 1))->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($celeste);
                }
                $finPedido[] = $c - 1;
            }
        }

        // Denso: letra chica y filas bajas para ver mas pedidos por pantalla.
        $ultima = max($c - 1, 2);
        $sheet->getStyle('A2:M' . $ultima)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach ($finPedido as $fila) {
            $sheet->getStyle("A{$fila}:M{$fila}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        }
        $sheet->getStyle('A3:M' . $ultima)->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('F3:F' . $ultima)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('H3:H' . $ultima)->getFont()->setBold(true);
        // La fila del cliente un poco mas grande que la de sus productos.
        foreach ($filasCliente as $fila) {
            $sheet->getStyle("A{$fila}:F{$fila}")->getFont()->setSize(11);
        }
        foreach ($celdasObs as $celda) {
            $sheet->getStyle($celda)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'C62828']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
            ]);
        }
        for ($fila = 3; $fila <= $ultima; $fila++) {
            $sheet->getRowDimension($fila)->setRowHeight(16);
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(4.5);
        }
        $sheet->getColumnDimension('F')->setWidth(32);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(6);
        foreach (['I', 'K', 'L'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(9);
        }
        $sheet->getColumnDimension('J')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(32);
        $sheet->freezePane('G3');
        $sheet->getSheetView()->setZoomScale(70);

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setPrintArea('A1:' . $colFin . $ultima)
            ->setRowsToRepeatAtTopByStartAndEnd(2, 2);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.2)->setRight(0.2);

        // Resumen: lo que hay que preparar por codigo, para cuadrar con stock.
        $resumen = $spreadsheet->createSheet();
        $resumen->setTitle('Resumen');
        $resumen->fromArray(['Código', 'Descripción', 'Unidades', 'Cajas', 'Kilos', 'Pedidos'], null, 'A1');
        $totales = [];
        foreach ($lineas as $l) {
            $t = &$totales[$l->cod_prod];
            if ($t === null) {
                $t = ['producto' => $l->producto, 'u' => 0, 'cja' => 0, 'kg' => 0, 'pedidos' => []];
            }
            $suf = explode(' ', $cantidad($l));
            $clave = in_array(end($suf), ['u', 'cja', 'kg'], true) ? end($suf) : 'u';
            $t[$clave] += (float) $l->Cant;
            $t['pedidos'][$l->NroPed] = true;
            unset($t);
        }
        ksort($totales);
        $fila = 2;
        foreach ($totales as $cod => $t) {
            $resumen->setCellValueExplicit('A' . $fila, $cod, DataType::TYPE_STRING);
            $resumen->setCellValue('B' . $fila, $t['producto']);
            $resumen->setCellValue('C' . $fila, $t['u'] ?: null);
            $resumen->setCellValue('D' . $fila, $t['cja'] ?: null);
            $resumen->setCellValue('E' . $fila, $t['kg'] ?: null);
            $resumen->setCellValue('F' . $fila, count($t['pedidos']));
            $fila++;
        }
        $resumen->getStyle('A1:F1')->getFont()->setBold(true);
        $resumen->getStyle('A1:F' . max($fila - 1, 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $resumen->getColumnDimension('A')->setWidth(10);
        $resumen->getColumnDimension('B')->setWidth(45);
        foreach (['C', 'D', 'E', 'F'] as $col) {
            $resumen->getColumnDimension($col)->setWidth(11);
        }

        $this->hojaPorVendedor($spreadsheet->createSheet(), $fecha, $especie, $codigos, $lineas, $cantidad);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'Preparacion_' . ucfirst($especie) . '_' . $fecha . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    /**
     * Que precio de la lista eligio el preventista: "Precio 3 · 25.00".
     *
     * Misma numeracion que la pantalla de pedidos: Precio es el 1, Precio_Costo
     * el 2 (nombre heredado, no es el costo) y despues Precio3..Precio13. Si
     * dos valen lo mismo cuenta el primero, como en la lista del preventista.
     * Un precio que no esta en la lista sale solo con el importe.
     */
    private function precioElegido($linea)
    {
        $precio = round((float) $linea->precio_pedido, 2);
        $campos = ['Precio', 'Precio_Costo', 'Precio3', 'Precio4', 'Precio5', 'Precio6', 'Precio7',
            'Precio8', 'Precio9', 'Precio10', 'Precio11', 'Precio12', 'Precio13'];
        foreach ($campos as $i => $campo) {
            $valor = round((float) ($linea->$campo ?? 0), 2);
            if ($valor > 0 && abs($valor - $precio) < 0.005) {
                return 'Precio ' . ($i + 1) . ' · ' . number_format($precio, 2, '.', '');
            }
        }
        return $precio > 0 ? 'Bs ' . number_format($precio, 2, '.', '') : '';
    }

    /**
     * Reporte del dia: cuanto pidio cada preventista de cada codigo, con el
     * total abajo. Van todos los codigos de la lista aunque nadie los haya
     * pedido, como la planilla de papel. Si un codigo se pidio en unidades
     * distintas (u, cja, kg) lleva una columna por unidad, porque no se suman.
     */
    private function hojaPorVendedor($hoja, $fecha, $especie, array $codigos, array $lineas, callable $cantidad)
    {
        $hoja->setTitle('Por vendedor');

        // [codigo][unidad][preventista] => suma; y las unidades en el orden en que aparecen.
        $sumas = [];
        $unidades = array_fill_keys($codigos, []);
        $nombres = [];
        $vendedores = [];
        foreach ($lineas as $l) {
            $partes = explode(' ', $cantidad($l));
            $unidad = count($partes) > 1 ? end($partes) : '';
            $prev = $l->preventista !== '' ? $l->preventista : 'SIN PREVENTISTA';
            $unidades[$l->cod_prod][$unidad] = true;
            $nombres[$l->cod_prod] = $l->producto;
            $vendedores[$prev] = true;
            $sumas[$l->cod_prod][$unidad][$prev] = ($sumas[$l->cod_prod][$unidad][$prev] ?? 0) + (float) $l->Cant;
        }
        ksort($vendedores);

        $columnas = [];
        foreach ($codigos as $cod) {
            $us = array_keys($unidades[$cod]) ?: [''];
            foreach ($us as $u) {
                $columnas[] = [$cod, $u];
            }
        }
        // Los codigos que nadie pidio tambien llevan su nombre.
        $faltan = array_values(array_diff($codigos, array_keys($nombres)));
        if ($faltan) {
            $nombres += DB::table('tbproductos')->whereIn(DB::raw('TRIM(cod_prod)'), $faltan)
                ->get([DB::raw('TRIM(cod_prod) as cod'), 'Producto'])
                ->pluck('Producto', 'cod')->all();
        }

        $colFin = Coordinate::stringFromColumnIndex(1 + count($columnas));
        $hoja->setCellValue('A1', 'REPORTE DEL DIA - ' . strtoupper($especie) . '   ' . $fecha);
        $hoja->mergeCells('A1:' . $colFin . '1');
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('C62828');

        $hoja->setCellValue('A2', 'Código');
        $hoja->setCellValue('A3', 'Producto');
        $hoja->setCellValue('A4', 'Unidad');
        foreach ($columnas as $i => [$cod, $u]) {
            $col = Coordinate::stringFromColumnIndex(2 + $i);
            $hoja->setCellValueExplicit($col . '2', $cod, DataType::TYPE_STRING);
            $hoja->setCellValue($col . '3', mb_substr(trim((string) ($nombres[$cod] ?? '')), 0, 3));
            $hoja->setCellValue($col . '4', $u);
        }
        $hoja->getStyle('A2:' . $colFin . '4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
        ]);

        $fila = 5;
        foreach (array_keys($vendedores) as $prev) {
            $hoja->setCellValue('A' . $fila, $prev);
            foreach ($columnas as $i => [$cod, $u]) {
                $valor = $sumas[$cod][$u][$prev] ?? null;
                if ($valor) {
                    $hoja->setCellValue(Coordinate::stringFromColumnIndex(2 + $i) . $fila, round($valor, 2));
                }
            }
            $fila++;
        }

        $hoja->setCellValue('A' . $fila, 'TOTAL');
        foreach ($columnas as $i => $_) {
            $col = Coordinate::stringFromColumnIndex(2 + $i);
            $hoja->setCellValue($col . $fila, $fila > 5 ? '=SUM(' . $col . '5:' . $col . ($fila - 1) . ')' : 0);
        }
        $hoja->getStyle('A' . $fila . ':' . $colFin . $fila)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8CBAD']],
        ]);

        $hoja->getStyle('A2:' . $colFin . $fila)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $hoja->getStyle('B2:' . $colFin . $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('B5:' . $colFin . $fila)->getNumberFormat()->setFormatCode('#,##0.##');
        $hoja->getStyle('A5:A' . $fila)->getFont()->setBold(true);
        $hoja->getColumnDimension('A')->setWidth(24);
        foreach ($columnas as $i => $_) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $i))->setWidth(8.5);
        }
        $hoja->freezePane('B5');
        $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)->setFitToHeight(0);
    }

    public function generarXlsBrasa($fecha)
    {
        $preventistas = DB::select("SELECT pe.Nombre1,pe.App1,pe.CodAut
            from personal pe inner join tbpedidos p on pe.CodAut=p.CIfunc
            where p.deleted_at IS NULL AND date(p.fecha)='$fecha' and tipo='POLLO'
             group by pe.Nombre1,pe.App1,pe.CodAut");

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('preparacion.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('G1', $fecha);

        $c = 4;
        foreach ($preventistas as $value) {

            $pedidos = DB::select("SELECT c.Nombres,p.fact,p.pago,p.bs,p.bs2, CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END as campo_pago,
            cbrasa5,
            ubrasa5,
            cbrasa6,
            cubrasa6,
            rango,
            Observaciones,
            horario
            from tbpedidos p  INNER join tbclientes c on p.idCli=c.Cod_Aut
            where p.deleted_at IS NULL AND p.CIfunc=" . $value->CodAut . " and date(fecha)='$fecha' and tipo='POLLO' AND estado='ENVIADO'
            and (rango IS NOT NULL or ubrasa5 IS NOT NULL or cbrasa5 IS NOT NULL or cbrasa6 IS NOT NULL or cubrasa6 IS NOT NULL)");
            if ($pedidos == null) {
                continue;
            }
            $sheet->setCellValue('F' . $c, trim($value->Nombre1) . ' ' . trim($value->App1));
            $c++;
            foreach ($pedidos as $r) {
                //                $t.=" ".$r->Nombres;git
                if ($r->horario == null) $r->horario = '';
                $sheet->setCellValue('A' . $c, $r->horario);
                $sheet->setCellValue('B' . $c, $r->fact);
                $sheet->setCellValue('C' . $c, $r->campo_pago);
                $sheet->setCellValue('D' . $c, $r->bs2);
                $sheet->setCellValue('E' . $c, $r->bs);
                $sheet->setCellValue('F' . $c, $r->Nombres);
                // productos por cliente
                $productos = [];

                // B5
                if ($r->cbrasa5 != null || $r->ubrasa5 != null) {
                    if ($r->cbrasa5 != null) {
                        $productos[] = ['B5', $r->cbrasa5 . ' cja'];
                    } else {
                        $productos[] = ['B5', $r->ubrasa5 . ' u'];
                    }
                } else {
                    $productos[] = ['B5', ''];
                }

                // B6
                if ($r->cbrasa6 != null || $r->cubrasa6 != null) {
                    if ($r->cbrasa6 != null) {
                        $productos[] = ['B6', $r->cbrasa6 . ' cja'];
                    } else {
                        $productos[] = ['B6', $r->cubrasa6 . ' u'];
                    }
                } else {
                    $productos[] = ['B6', ''];
                }

                if ($r->rango != null) {
                    $productos[] = ['Rango', $r->rango . ' u'];
                } else {
                    $productos[] = ['Rango', ''];
                }


// iniciar en la columna G (índice 6, porque A=0)
                $col = 7;

                foreach ($productos as $prod) {
                    if ($prod[1] != '') {
                        $colLetter1 = Coordinate::stringFromColumnIndex($col);       // por ejemplo 'A'
                        $colLetter2 = Coordinate::stringFromColumnIndex($col + 1);   // por ejemplo 'B'

                        $cell1 = $colLetter1 . $c; // ej: 'A5'
                        $cell2 = $colLetter2 . $c; // ej: 'B5'

                        $sheet->setCellValue($cell1, $prod[0]);     // Nombre
                        $sheet->setCellValue($cell2, $prod[1]);     // Cantidad

                        // Por defecto: color negro
                        $sheet->getStyle($cell1)->getFont()->setBold(false)->getColor()->setARGB('000000');
                        $sheet->getStyle($cell2)->getFont()->setBold(false)->getColor()->setARGB('000000');


                        // Si es 'Rango', color rojo
                        if ($prod[0] == 'Rango') {
                            $sheet->getStyle($cell1)->getFont()->setBold(true)->getColor()->setARGB('FF0000');
                            $sheet->getStyle($cell2)->getFont()->setBold(true)->getColor()->setARGB('FF0000');
                        }

                        $col += 4;
                    }

                }
                if ($r->Observaciones != null) {
                    $colLetter1 = Coordinate::stringFromColumnIndex($col);
                    $cell1 = $colLetter1 . $c;
                    $sheet->setCellValue($cell1, $r->Observaciones);
                    $sheet->getStyle($cell1)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            //'vertical'   => Alignment::VERTICAL_TOP,
                            //'indent'     => 1, // Sangría (equivale a padding del lado izquierdo)
                            //'wrapText'   => true, // Opcional: si el texto es largo, se ajusta
                        ],
                    ]);
                }

                $c++;

            }
        }
        $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
        $date = str_replace(".", "", $date);
        $filename = "Frial_Brasa_" . $date . ".xlsx";
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);
        } catch (Exception $e) {
            exit($e->getMessage());
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

        echo $content;  // this actually send the file content to the browser

        unlink($filename);

    }


    public function generarXlsCerdo($fecha)
    {
        $preventistas = DB::select(
            "SELECT pe.Nombre1, pe.App1, pe.CodAut
         FROM personal pe
         INNER JOIN tbpedidos p ON pe.CodAut = p.CIfunc
         WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ? AND p.tipo = 'CERDO'
         GROUP BY pe.Nombre1, pe.App1, pe.CodAut",
            [$fecha]
        );

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('prepcerdo.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('J3', $fecha);

        // Mapa de colores
        $mapaColores = [
            'deep-orange-4' => 'FF7043', // NORTE
            'pink-4' => 'F06292', // BOLIVAR
            'blue-grey-4' => '37474F', // SE RECOGE
            'yellow' => 'F5EE17', // CENTRO
            'green-4' => '1B5E20', // APOYO
            'green-8' => '2E7D32', // APOYO2
            'deep-purple-4' => '9575CD', // HUANUNI
            'red-10' => 'B71C1C', // CHALLAPATA
            'red-4' => 'E57373', // LLALLAGUA
            'light-blue-8' => '0288D1', // CARACOLLO
            'blue-4' => '0D47A1', // SUD
            'amber-8' => 'FFB300', // MOTO1
            'grey-6' => '757575', // SIN ZONA
        ];

        // Función para decidir si usar texto blanco o negro
        $isDark = function (string $hex) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            return $luminance < 150;
        };

        $c = 6;

        foreach ($preventistas as $value) {
            $sheet->setCellValue('B' . $c, trim($value->Nombre1) . ' ' . trim($value->App1));
            $c++;

            $pedidos = DB::select(
                "SELECT c.Nombres, p.fact, p.pago, p.total, p.entero, p.desmembre, p.corte, p.kilo,p.pfrial,
                    p.Observaciones, p.color,p.horario,p.comentario,
                    CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END AS campo_pago,
                    bonificacionId
             FROM tbpedidos p
             INNER JOIN tbclientes c ON p.idCli = c.Cod_Aut
             WHERE p.deleted_at IS NULL AND p.CIfunc = ? AND DATE(p.fecha) = ? AND p.tipo = 'CERDO' AND p.estado = 'ENVIADO'",
                [$value->CodAut, $fecha]
            );
            $cliente3070 = DB::table('tbclientes')->where('Cod_Aut', 3070)->value('Nombres');
            $cliente2728 = DB::table('tbclientes')->where('Cod_Aut', 2728)->value('Nombres');

            foreach ($pedidos as $r) {
                $unid = 0;
                if ($r->kilo)
                    $unid = $r->kilo / 10;
                $entero = 0;
                if ($r->entero) $entero = $r->entero;
                $desmembre = 0;
                if ($r->desmembre) $desmembre = $r->desmembre;
                $corte = 0;
                if ($r->corte) $corte = $r->corte;
                $total = $r->total ?? ($entero + $desmembre + $corte + $unid);
                $sheet->setCellValue('B' . $c, $r->bonificacionId == null ? $r->Nombres : ($r->bonificacionId == 3070 ? $cliente3070 : ($r->bonificacionId == 2728 ? $cliente2728 : $r->Nombres)));
                $sheet->setCellValue('C' . $c, $r->pfrial);
                $sheet->setCellValue('D' . $c, $total);
                $sheet->setCellValue('E' . $c, $r->entero);
                $sheet->setCellValue('F' . $c, $r->desmembre);
                $sheet->setCellValue('H' . $c, $r->corte);
                $sheet->setCellValue('I' . $c, $r->kilo);
                $sheet->setCellValue('J' . $c, $r->Observaciones);
                $sheet->setCellValue('U' . $c, $r->campo_pago);
                $sheet->setCellValue('V' . $c, $r->fact);
                $sheet->setCellValue('W' . $c, $r->horario);
//                $sheet->setCellValue('X'.$c,$r->comentario);
                $sheet->setCellValue('X' . $c, $r->bonificacionId == null ? $r->comentario : $r->Nombres . $r->comentario);

                // === Color SOLO en el nombre del cliente (B{fila}) ===
                if (!empty($r->color) && isset($mapaColores[$r->color])) {
                    $hex = $mapaColores[$r->color];
                    $celdaNombre = "B{$c}";

                    $sheet->getStyle($celdaNombre)->applyFromArray([
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'color' => ['rgb' => $hex]
                        ]
                    ]);

                    $fontColor = $isDark($hex) ? 'FFFFFF' : '000000';
                    $sheet->getStyle($celdaNombre)->getFont()->getColor()->setRGB($fontColor);
                }

                $c++;
            }
        }

        $date = date('d-m-y-' . substr((string)microtime(), 1, 8));
        $date = str_replace(".", "", $date);
        $filename = "Frial_Cerdo_" . $date . ".xlsx";

        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filename);
            $content = file_get_contents($filename);
        } catch (\Exception $e) {
            exit($e->getMessage());
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');

        echo $content;
        @unlink($filename);
    }

    public function generarXlsPollo3($fecha)
    {
        $vendedores = DB::select("
        SELECT pe.CodAut, CONCAT(TRIM(pe.Nombre1), ' ', TRIM(pe.App1)) as nombre
        FROM personal pe
        JOIN tbpedidos p ON p.CIfunc = pe.CodAut
        WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ? AND p.tipo = 'POLLO' AND p.estado = 'ENVIADO'
        GROUP BY pe.CodAut, pe.Nombre1, pe.App1
        ORDER BY nombre ASC
    ", [$fecha]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("PEDIDOS DE POLLOS");

        // Mostrar la FECHA en la parte superior
        $sheet->mergeCells("A1:H1");
        $sheet->setCellValue("A1", "FECHA DE PEDIDO: $fecha");
        $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A1")->getFont()->getColor()->setARGB('FF0000');
        $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $fila = 3; // Comenzamos después de la fecha (fila 1) y espacio (fila 2)

        foreach ($vendedores as $vendedor) {
            $sheet->mergeCells("A{$fila}:H{$fila}");
            $sheet->setCellValue("A{$fila}", strtoupper($vendedor->nombre));
            $sheet->getStyle("A{$fila}")->getFont()->setBold(true)->getColor()->setARGB('FF0000');
            $fila++;

            $cabeceras = [
                'No', 'CLIENTE', 'Brasa 5 cja.', 'Brasa 5 und', 'Brasa 6 cja.', 'Brasa 6 und',
                '104 cja.', '104 und', '105 cja.', '105 und', '106 cja.', '106 und',
                '107 cja.', '107 und', '108 cja.', '108 und', '109 cja.', '109 und',
                'Rango', 'Ala', 'Cadera', 'Pecho', 'Pj/Mu', 'Filete', 'Cuello', 'Hueso', 'Menud',
                'Bs.', 'Bs. 2', 'Ctdad', 'Observaciones', 'Fact'
            ];

            foreach ($cabeceras as $col => $titulo) {
                $columna = Coordinate::stringFromColumnIndex(1 + $col); // A = 1
                $sheet->setCellValue("{$columna}{$fila}", $titulo);
                $sheet->getStyle("{$columna}{$fila}")->getFont()->setBold(true);
                $sheet->getStyle("{$columna}{$fila}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDDD');
                $sheet->getColumnDimension($columna)->setAutoSize(true);
            }

            $fila++;

            $pedidos = DB::select("
            SELECT c.Nombres,
                p.cbrasa5, p.ubrasa5, p.cbrasa6, p.cubrasa6,
                p.c104, p.u104, p.c105, p.u105, p.c106, p.u106,
                p.c107, p.u107, p.c108, p.u108, p.c109, p.u109,
                p.rango,
                p.ala, p.unidala,
                p.cadera, p.unidcadera,
                p.pecho, p.unidpecho,
                p.pie, p.unidpie,
                p.filete, p.unidfilete,
                p.cuello, p.unidcuello,
                p.hueso, p.unidhueso,
                p.menu, p.unidmenu,
                p.bs, p.bs2, p.pago, p.Observaciones, p.fact
            FROM tbpedidos p
            JOIN tbclientes c ON c.Cod_Aut = p.idCli
            WHERE p.deleted_at IS NULL AND DATE(p.fecha) = ? AND p.tipo = 'POLLO' AND p.estado = 'ENVIADO' AND p.CIfunc = ?
        ", [$fecha, $vendedor->CodAut]);

            $num = 1;

            foreach ($pedidos as $p) {
                $sheet->setCellValue("A{$fila}", $num++);
                $sheet->setCellValue("B{$fila}", $p->Nombres);
                $sheet->setCellValue("C{$fila}", $p->cbrasa5);
                $sheet->setCellValue("D{$fila}", $p->ubrasa5);
                $sheet->setCellValue("E{$fila}", $p->cbrasa6);
                $sheet->setCellValue("F{$fila}", $p->cubrasa6);
                $sheet->setCellValue("G{$fila}", $p->c104);
                $sheet->setCellValue("H{$fila}", $p->u104);
                $sheet->setCellValue("I{$fila}", $p->c105);
                $sheet->setCellValue("J{$fila}", $p->u105);
                $sheet->setCellValue("K{$fila}", $p->c106);
                $sheet->setCellValue("L{$fila}", $p->u106);
                $sheet->setCellValue("M{$fila}", $p->c107);
                $sheet->setCellValue("N{$fila}", $p->u107);
                $sheet->setCellValue("O{$fila}", $p->c108);
                $sheet->setCellValue("P{$fila}", $p->u108);
                $sheet->setCellValue("Q{$fila}", $p->c109);
                $sheet->setCellValue("R{$fila}", $p->u109);
                $sheet->setCellValue("S{$fila}", $p->rango);
                $sheet->setCellValue("T{$fila}", $p->ala ? "{$p->ala} {$p->unidala}" : '');
                $sheet->setCellValue("U{$fila}", $p->cadera ? "{$p->cadera} {$p->unidcadera}" : '');
                $sheet->setCellValue("V{$fila}", $p->pecho ? "{$p->pecho} {$p->unidpecho}" : '');
                $sheet->setCellValue("W{$fila}", $p->pie ? "{$p->pie} {$p->unidpie}" : '');
                $sheet->setCellValue("X{$fila}", $p->filete ? "{$p->filete} {$p->unidfilete}" : '');
                $sheet->setCellValue("Y{$fila}", $p->cuello ? "{$p->cuello} {$p->unidcuello}" : '');
                $sheet->setCellValue("Z{$fila}", $p->hueso ? "{$p->hueso} {$p->unidhueso}" : '');
                $sheet->setCellValue("AA{$fila}", $p->menu ? "{$p->menu} {$p->unidmenu}" : '');
                $sheet->setCellValue("AB{$fila}", $p->bs);
                $sheet->setCellValue("AC{$fila}", $p->bs2);
                $sheet->setCellValue("AD{$fila}", strtolower($p->pago) == 'contado' ? 'si' : 'no');
                $sheet->setCellValue("AE{$fila}", $p->Observaciones);
                $sheet->setCellValue("AF{$fila}", $p->fact);

                $fila++;
            }

            $fila++; // Espacio entre vendedores
        }

        // Zoom general
        $sheet->getSheetView()->setZoomScale(60);

        $filename = 'PEDIDOS_POLLOS_' . date('Ymd_His') . '.xlsx';
        $tempPath = storage_path($filename);
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }
}
