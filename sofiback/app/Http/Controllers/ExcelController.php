<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
            $preventistas = DB::select("SELECT pe.Nombre1,pe.App1,pe.CodAut from personal pe inner join tbpedidos p on pe.CodAut=p.CIfunc
            where date(p.fecha)='$fecha' and tipo='POLLO' group by pe.Nombre1,pe.App1,pe.CodAut");

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('preparacion.xlsx');
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('G1', $fecha);

        $c=4;
        foreach ($preventistas as $value) {
            $sheet->setCellValue('F'.$c, trim($value->Nombre1).' '.trim($value->App1));
            $c++;
            $pedidos=DB::select("SELECT c.Nombres,p.fact,p.pago,p.bs,p.bs2, CASE WHEN p.pago = 'CONTADO' THEN 'SI' ELSE 'NO' END as campo_pago
            from tbpedidos p  INNER join tbclientes c on p.idCli=c.Cod_Aut
            where p.CIfunc=".$value->CodAut." and date(fecha)='$fecha' and tipo='POLLO' AND estado='ENVIADO' ");

            foreach ($pedidos as $r){
        //                $t.=" ".$r->Nombres;git
                $sheet->setCellValue('B'.$c, $r->fact);
                $sheet->setCellValue('C'.$c, $r->campo_pago);
                $sheet->setCellValue('D'.$c, $r->bs2);
                $sheet->setCellValue('E'.$c, $r->bs);
                $sheet->setCellValue('F'.$c, $r->Nombres);
                $c++;
            # code...

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
}
