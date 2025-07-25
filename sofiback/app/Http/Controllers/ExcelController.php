<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelController extends Controller
{
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($fecha){
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
        WHERE DATE(p.fecha) = ?
          AND p.tipo IN ('POLLO', 'RES', 'CERDO')
        GROUP BY pe.Nombre1, pe.App1, pe.CodAut
    ", [$fecha]);
    }

    public function consulta($t,$f1,$f2,$codaut)
    {
        if ($t=='p'){
            $persona=DB::table('personal')->where('CodAut',$codaut)->first();
            $query= DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='POLLO' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t='';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ppollo.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c=4;
            $sheet->setCellValue('E2', trim($persona->Nombre1).' '.trim($persona->App1));
            $sheet->setCellValue('AB2', $f1);
            foreach ($query as $r){
//                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->Nombres);
                $sheet->setCellValue('C'.$c, $r->cbrasa5);
                $sheet->setCellValue('D'.$c, $r->ubrasa5);
                $sheet->setCellValue('E'.$c, $r->cbrasa6);
                $sheet->setCellValue('F'.$c, $r->cubrasa6);
                $sheet->setCellValue('G'.$c, $r->c104);
                $sheet->setCellValue('H'.$c, $r->u104);
                $sheet->setCellValue('I'.$c, $r->c105);
                $sheet->setCellValue('J'.$c, $r->u105);
                $sheet->setCellValue('K'.$c, $r->c106);
                $sheet->setCellValue('L'.$c, $r->u106);
                $sheet->setCellValue('M'.$c, $r->c107);
                $sheet->setCellValue('N'.$c, $r->u107);
                $sheet->setCellValue('O'.$c, $r->c108);
                $sheet->setCellValue('P'.$c, $r->u108);
                $sheet->setCellValue('Q'.$c, $r->c109);
                $sheet->setCellValue('R'.$c, $r->u109);
                $sheet->setCellValue('S'.$c, $r->rango);
                $sheet->setCellValue('T'.$c, $r->ala==''?'':$r->ala.''.$r->unidala);
                $sheet->setCellValue('U'.$c, $r->cadera==''?'':$r->cadera.''.$r->unidcadera);
                $sheet->setCellValue('V'.$c, $r->pecho==''?'':$r->pecho.''.$r->unidpecho);
                $sheet->setCellValue('W'.$c, $r->pie==''?'':$r->pie.''.$r->unidpie);
                $sheet->setCellValue('X'.$c, $r->filete==''?'':$r->filete.''.$r->unidfilete);
                $sheet->setCellValue('Y'.$c, $r->cuello==''?'':$r->cuello.''.$r->unidcuello);
                $sheet->setCellValue('Z'.$c, $r->hueso==''?'':$r->hueso.''.$r->unidhueso);
                $sheet->setCellValue('AA'.$c, $r->menu==''?'':$r->menu.''.$r->unidmenu);
                $sheet->setCellValue('AB'.$c, $r->bs);
                $sheet->setCellValue('AC'.$c, $r->bs2);
                $sheet->setCellValue('AD'.$c, $r->pago=='CONTADO'?'si':'no');
                $sheet->setCellValue('AE'.$c, $r->Observaciones);
                $sheet->setCellValue('AF'.$c, $r->fact);
                $sheet->setCellValue('AG'.$c, $r->horario);
                $sheet->setCellValue('AH'.$c, $r->comentario);
                $c++;
            }
//            return $t;
            //        $spreadsheet = new Spreadsheet();

//            $sheet->setCellValue('A1', 'ID');


// Write an .xlsx file
            $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1).'_'.trim($persona->App1)."_".$date.".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch(Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

//        return $t;


        if ($t=='r'){
            $persona=DB::table('personal')->where('CodAut',$codaut)->first();
            $query= DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='RES' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t='';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('pres.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c=6;
            $sheet->setCellValue('C3', trim($persona->Nombre1).' '.trim($persona->App1));
            $sheet->setCellValue('J3', $f1);
            foreach ($query as $r){
//                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->Nombres);
                $sheet->setCellValue('C'.$c, $r->pfrial);
                $sheet->setCellValue('D'.$c, $r->trozado);
                $sheet->setCellValue('E'.$c, $r->entero);
                $sheet->setCellValue('F'.$c, $r->pierna);
                $sheet->setCellValue('G'.$c, $r->brazo);
                $sheet->setCellValue('J'.$c, $r->Observaciones);
                $sheet->setCellValue('K'.$c, $r->pago=='CONTADO'?'si':'no');
                $sheet->setCellValue('L'.$c, $r->fact);
                $sheet->setCellValue('M'.$c, $r->hoario);
                $sheet->setCellValue('N'.$c, $r->comentario);
                $c++;
            }
//            return $t;
            //        $spreadsheet = new Spreadsheet();

//            $sheet->setCellValue('A1', 'ID');


// Write an .xlsx file
            $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1).'_'.trim($persona->App1)."_res_".$date.".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch(Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

//        return $t;
        if ($t=='c'){
            $persona=DB::table('personal')->where('CodAut',$codaut)->first();
            $query= DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where c.Cod_Aut=p.idCli and date(fecha)='$f1'
            and tipo='CERDO' AND CIfunc='$codaut' AND estado='ENVIADO' ");
            $t='';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('pcerdo.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c=6;
            $sheet->setCellValue('C3', trim($persona->Nombre1).' '.trim($persona->App1));
            $sheet->setCellValue('J3', $f1);
            foreach ($query as $r){
        //                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->Nombres);
                $sheet->setCellValue('C'.$c, $r->pfrial);
                //$sheet->setCellValue('D'.$c, $r->trozado);
                $sheet->setCellValue('E'.$c, $r->entero);
                $sheet->setCellValue('F'.$c, $r->desmembre);
                $sheet->setCellValue('H'.$c, $r->corte);
                $sheet->setCellValue('I'.$c, $r->kilo);
                $sheet->setCellValue('J'.$c, $r->Observaciones);
                $sheet->setCellValue('U'.$c, $r->pago=='CONTADO'?'si':'no');
                $sheet->setCellValue('V'.$c, $r->fact);
                $sheet->setCellValue('W'.$c, $r->horario);
                $sheet->setCellValue('X'.$c, $r->comentario);
                $c++;
            }
        //            return $t;
            //        $spreadsheet = new Spreadsheet();

        //            $sheet->setCellValue('A1', 'ID');


        // Write an .xlsx file
            $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = trim($persona->Nombre1).'_'.trim($persona->App1)."_cerd_".$date.".xlsx";
            $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->save($filename);
                $content = file_get_contents($filename);
            } catch(Exception $e) {
                exit($e->getMessage());
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );

            echo $content;  // this actually send the file content to the browser

            unlink($filename);
        }

    }

    public function reporteEmbutido(Request $request){
        return DB::SELECT("SELECT c.Id, c.Nombres, c.Direccion, c.Telf, p.NroPed, p.cod_prod, p.idCli , p.Cant , p.precio , p.fecha , p.Observaciones , p.subtotal, u.Producto, p.pago,p.fact,p.horario.p.comentario
         FROM tbpedidos p inner join tbclientes c on p.idCli=c.Cod_Aut inner join tbproductos u on u.cod_prod=p.cod_prod
          where p.tipo='NORMAL' AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.estado='ENVIADO' AND CIfunc='$request->codaut'");
    }

    public function reporteCerdo(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c
        where c.Cod_Aut=p.idCli and date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and tipo='CERDO' AND CIfunc='$request->codaut' AND estado='ENVIADO' ");
    }

    public function reporteCerdoTodo(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p inner join tbclientes c on c.Cod_Aut=p.idCli  inner join personal e on p.CIfunc=e.CodAut
        where  date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and p.tipo='CERDO' AND p.estado='ENVIADO' ");
    }

    public function reporteEmbutidoTodo(Request $request){
        return DB::SELECT("SELECT c.Id, c.Nombres, c.Direccion, c.Telf, p.NroPed, p.cod_prod, p.idCli , p.Cant , p.precio , p.fecha , p.Observaciones , p.subtotal,
         u.Producto, p.pago,p.fact, e.Nombre1,e.App1,e.Apm,p.horario,p.comentario
         FROM tbpedidos p inner join tbclientes c on p.idCli=c.Cod_Aut inner join tbproductos u on u.cod_prod=p.cod_prod inner join personal e on p.CIfunc=e.CodAut
          where p.tipo='NORMAL' AND date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin'  ");
    }// and p.estado='ENVIADO'

    public function reportePollo(Request $request){
        return DB::SELECT("SELECT * from tbpedidos p, tbclientes c
        where c.Cod_Aut=p.idCli and date(fecha)>='$request->ini' and date(fecha)<='$request->fin'
        and tipo='POLLO' AND CIfunc='$request->codaut' AND estado='ENVIADO' ");
    }

    public function listregistro(Request $request){
        return DB::SELECT("SELECT pe.Nombre1,pe.App1,pe.CodAut
        FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc
        WHERE date(p.fecha)>='$request->ini' AND date(p.fecha)<='$request->fin' GROUP BY pe.Nombre1,pe.App1,pe.CodAut;");
    }

    public function reportePollo2(Request $request){
        return DB::SELECT("
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'ubrasa5' producto,p.ubrasa5 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.ubrasa5 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'cbrasa5' producto,p.cbrasa5 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cbrasa5 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'ubrasa6' producto,p.cubrasa6 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cubrasa6 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'cbrasa6' producto,p.cbrasa6 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cbrasa6 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u104' producto,p.u104 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u104 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c104' producto,p.c104 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c104 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u105' producto,p.u105 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u105 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c105' producto,p.c105 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c105 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u106' producto,p.u106 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u106 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c106' producto,p.c106 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c106 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u107' producto,p.u107 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u107 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c107' producto,p.c107 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c107 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u108' producto,p.u108 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u108 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c108' producto,p.c108 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c108 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'u109' producto,p.u109 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.u109 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'c109' producto,p.c109 cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.c109 is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'rango' producto,p.rango cantidad,p.bs precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.rango is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'ala' producto,concat(p.ala,' ',p.unidala) cantidad ,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.ala is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'cadera' producto,concat(p.cadera,' ',p.unidcadera) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cadera is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'pecho' producto,concat(p.pecho,' ',p.unidpecho) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.pecho is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'pie' producto,concat(p.pie,' ',p.unidpie) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.pie is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'filete' producto,concat(p.filete,' ',p.unidfilete) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.filete is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'cuello' producto,concat(p.cuello,' ',p.unidcuello) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.cuello is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'hueso' producto,concat(p.hueso,' ',p.unidhueso) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.hueso is NOT null union
          SELECT concat(pe.Nombre1,' ',pe.App1,' ',pe.Apm) preventista,c.Nombres,p.fecha,p.Observaciones,'menu' producto,concat(p.menu,' ',p.unidmenu) cantidad,p.bs2 precio,p.fact,p.pago,p.horario,p.comentario FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc inner join tbclientes c on p.idCli=c.Cod_Aut where date(p.fecha)>='$request->ini' and date(p.fecha)<='$request->fin' and p.tipo='POLLO' and p.menu is NOT null

          ");

    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function generarXlsPollo($fecha){
            $preventistas = DB::select("SELECT pe.Nombre1,pe.App1,pe.CodAut
            from personal pe inner join tbpedidos p on pe.CodAut=p.CIfunc
            where date(p.fecha)='$fecha' and tipo='POLLO' group by pe.Nombre1,pe.App1,pe.CodAut");

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('preparacion.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('G1', $fecha);

        $c=4;
        foreach ($preventistas as $value) {
            $sheet->setCellValue('F'.$c, trim($value->Nombre1).' '.trim($value->App1));
            $c++;
            $pedidos=DB::select("SELECT c.Nombres,p.fact,p.pago,p.bs,p.bs2, CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END as campo_pago,
                        cbrasa5,
            ubrasa5,
            cbrasa6,
            cubrasa6,
            c104,
            u104,
            c105,
            u105,
            c106,
            u106,
            c107,
            u107,
            c108,
            u108,
            c109,
            u109,
            rango,
            ala,
            unidala,
            cadera,
            unidcadera,
            pecho,
            unidpecho,
            pie,
            unidpie,
            filete,
            unidfilete,
            cuello,
            unidcuello,
            hueso,
            unidhueso,
            menu,
            unidmenu
            from tbpedidos p  INNER join tbclientes c on p.idCli=c.Cod_Aut
            where p.CIfunc=".$value->CodAut." and date(fecha)='$fecha' and tipo='POLLO' AND estado='ENVIADO' ");

            foreach ($pedidos as $r){
        //                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->fact);
                $sheet->setCellValue('C'.$c, $r->campo_pago);
                $sheet->setCellValue('D'.$c, $r->bs2);
                $sheet->setCellValue('E'.$c, $r->bs);
                $sheet->setCellValue('F'.$c, $r->Nombres);
      // productos por cliente
            $productos = [];

            // B5
            if ($r->cbrasa5 != null || $r->ubrasa5 != null) {
                if ($r->cbrasa5 != null) {
                    $productos[] = ['B5', $r->cbrasa5 . ' CJA'];
                } else {
                    $productos[] = ['B5', $r->ubrasa5 . ' U'];
                }
            } else {
                $productos[] = ['B5', ''];
            }

            // B6
            if ($r->cbrasa6 != null || $r->cubrasa6 != null) {
                if ($r->cbrasa6 != null) {
                    $productos[] = ['B6', $r->cbrasa6 . ' CJA'];
                } else {
                    $productos[] = ['B6', $r->cubrasa6 . ' U'];
                }
            } else {
                $productos[] = ['B6', ''];
            }

            // 104
            if ($r->c104 != null || $r->u104 != null) {
                if ($r->c104 != null) {
                    $productos[] = ['104', $r->c104 . ' CJA'];
                } else {
                    $productos[] = ['104', $r->u104 . ' U'];
                }
            } else {
                $productos[] = ['104', ''];
            }

            // 105
            if ($r->c105 != null || $r->u105 != null) {
                if ($r->c105 != null) {
                    $productos[] = ['105', $r->c105 . ' CJA'];
                } else {
                    $productos[] = ['105', $r->u105 . ' U'];
                }
            } else {
                $productos[] = ['105', ''];
            }

            // 106
            if ($r->c106 != null || $r->u106 != null) {
                if ($r->c106 != null) {
                    $productos[] = ['106', $r->c106 . ' CJA'];
                } else {
                    $productos[] = ['106', $r->u106 . ' U'];
                }
            } else {
                $productos[] = ['106', ''];
            }

            // 107
            if ($r->c107 != null || $r->u107 != null) {
                if ($r->c107 != null) {
                    $productos[] = ['107', $r->c107 . ' CJA'];
                } else {
                    $productos[] = ['107', $r->u107 . ' U'];
                }
            } else {
                $productos[] = ['107', ''];
            }

            // 108
            if ($r->c108 != null || $r->u108 != null) {
                if ($r->c108 != null) {
                    $productos[] = ['108', $r->c108 . ' CJA'];
                } else {
                    $productos[] = ['108', $r->u108 . ' U'];
                }
            } else {
                $productos[] = ['108', ''];
            }

            // 109
            if ($r->c109 != null || $r->u109 != null) {
                if ($r->c109 != null) {
                    $productos[] = ['109', $r->c109 . ' CJA'];
                } else {
                    $productos[] = ['109', $r->u109 . ' U'];
                }
            } else {
                $productos[] = ['109', ''];
            }

            // Rango (directo)
            $productos[] = ['Rango', $r->rango ?? ''];

            // Ala
            if ($r->ala != null) {
                $productos[] = ['Ala', $r->ala . ' ' . ($r->unidala ?? '')];
            } else {
                $productos[] = ['Ala', ''];
            }

            // Cadera
            if ($r->cadera != null) {
                $productos[] = ['Cadera', $r->cadera . ' ' . ($r->unidcadera ?? '')];
            } else {
                $productos[] = ['Cadera', ''];
            }

            // Pecho
            if ($r->pecho != null) {
                $productos[] = ['Pecho', $r->pecho . ' ' . ($r->unidpecho ?? '')];
            } else {
                $productos[] = ['Pecho', ''];
            }

            // Pie
            if ($r->pie != null) {
                $productos[] = ['Pie', $r->pie . ' ' . ($r->unidpie ?? '')];
            } else {
                $productos[] = ['Pie', ''];
            }

            // Filete
            if ($r->filete != null) {
                $productos[] = ['Filete', $r->filete . ' ' . ($r->unidfilete ?? '')];
            } else {
                $productos[] = ['Filete', ''];
            }

            // Cuello
            if ($r->cuello != null) {
                $productos[] = ['Cuello', $r->cuello . ' ' . ($r->unidcuello ?? '')];
            } else {
                $productos[] = ['Cuello', ''];
            }

            // Hueso
            if ($r->hueso != null) {
                $productos[] = ['Hueso', $r->hueso . ' ' . ($r->unidhueso ?? '')];
            } else {
                $productos[] = ['Hueso', ''];
            }

            // Menú
            if ($r->menu != null) {
                $productos[] = ['Menu', $r->menu . ' ' . ($r->unidmenu ?? '')];
            } else {
                $productos[] = ['Menu', ''];
            }

// iniciar en la columna G (índice 6, porque A=0)
    $col = 7;

    foreach ($productos as $prod) {
        if($prod[1] != '') {
            $sheet->setCellValueByColumnAndRow($col, $c, $prod[0]);     // Nombre
            $sheet->setCellValueByColumnAndRow($col + 1, $c, $prod[1]); // Cantidad
        
        $col += 4;
        }
    }

    $c++;

        }
    }
    $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
    $date = str_replace(".", "", $date);
    $filename = "Frial_Pollo_".$date.".xlsx";
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
    try {
        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);
        $content = file_get_contents($filename);
    } catch(Exception $e) {
        exit($e->getMessage());
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );

    echo $content;  // this actually send the file content to the browser

    unlink($filename);

    }

        public function generarXlsCerdo($fecha){
            $preventistas = DB::select("SELECT pe.Nombre1,pe.App1,pe.CodAut from personal pe inner join tbpedidos p on pe.CodAut=p.CIfunc
            where date(p.fecha)='$fecha' and tipo='CERDO' group by pe.Nombre1,pe.App1,pe.CodAut");

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('prepcerdo.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('J3', $fecha);

        $c=6;
        foreach ($preventistas as $value) {
            $sheet->setCellValue('B'.$c, trim($value->Nombre1).' '.trim($value->App1));
            $c++;
            $pedidos=DB::select("SELECT c.Nombres,p.fact,p.pago,p.total,p.entero,p.desmembre,p.corte,p.kilo,p.Observaciones, CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END as campo_pago
            from tbpedidos p  INNER join tbclientes c on p.idCli=c.Cod_Aut
            where p.CIfunc=".$value->CodAut." and date(fecha)='$fecha' and tipo='CERDO' AND estado='ENVIADO' ");

            foreach ($pedidos as $r){
        //                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->Nombres);
                $sheet->setCellValue('D'.$c, $r->total);
                $sheet->setCellValue('E'.$c, $r->entero);
                $sheet->setCellValue('F'.$c, $r->desmembre);
                $sheet->setCellValue('H'.$c, $r->corte);
                $sheet->setCellValue('I'.$c, $r->kilo);
                $sheet->setCellValue('J'.$c, $r->Observaciones);
                $sheet->setCellValue('U'.$c, $r->campo_pago);
                $sheet->setCellValue('V'.$c, $r->fact);
                $c++;
            # code...

        }
    }
    $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
    $date = str_replace(".", "", $date);
    $filename = "Frial_Cerdo_".$date.".xlsx";
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $filename; //make sure you set the right permissions and change this to the path you want
    try {
        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);
        $content = file_get_contents($filename);
    } catch(Exception $e) {
        exit($e->getMessage());
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . urlencode($filename) .'"' );

    echo $content;  // this actually send the file content to the browser

    unlink($filename);

    }

    public function generarXlsPollo3($fecha)
    {
        $vendedores = DB::select("
        SELECT pe.CodAut, CONCAT(TRIM(pe.Nombre1), ' ', TRIM(pe.App1)) as nombre
        FROM personal pe
        JOIN tbpedidos p ON p.CIfunc = pe.CodAut
        WHERE DATE(p.fecha) = ? AND p.tipo = 'POLLO' AND p.estado = 'ENVIADO'
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
            WHERE DATE(p.fecha) = ? AND p.tipo = 'POLLO' AND p.estado = 'ENVIADO' AND p.CIfunc = ?
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
