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
    public function show($fecha)
    {
        return DB::select("
        SELECT pe.Nombre1,pe.App1,pe.CodAut,
        (
        SELECT count(*)
        FROM tbpedidos p2
        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='POLLO'
        ) as pollo,
        (
        SELECT count(*)
        FROM tbpedidos p2
        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='RES'
        ) as res,
        (
        SELECT count(*)
        FROM tbpedidos p2
        WHERE date(p2.fecha)='".$fecha."' AND p2.CIfunc=pe.CodAut AND tipo='CERDO'
        ) as cerdo
        FROM tbpedidos p INNER JOIN personal pe ON pe.CodAut=p.CIfunc
        WHERE date(fecha)='".$fecha."' AND tipo IN ('POLLO','RES','CERDO')
        GROUP BY pe.Nombre1,pe.App1,pe.CodAut;");
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
            $sheet->setCellValue('AA2', $f1);
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
                $sheet->setCellValue('S'.$c, $r->ala==''?'':$r->ala.''.$r->unidala);
                $sheet->setCellValue('T'.$c, $r->cadera==''?'':$r->cadera.''.$r->unidcadera);
                $sheet->setCellValue('U'.$c, $r->pecho==''?'':$r->pecho.''.$r->unidpecho);
                $sheet->setCellValue('V'.$c, $r->pie==''?'':$r->pie.''.$r->unidpie);
                $sheet->setCellValue('W'.$c, $r->filete==''?'':$r->filete.''.$r->unidfilete);
                $sheet->setCellValue('X'.$c, $r->cuello==''?'':$r->cuello.''.$r->unidcuello);
                $sheet->setCellValue('Y'.$c, $r->hueso==''?'':$r->hueso.''.$r->unidhueso);
                $sheet->setCellValue('Z'.$c, $r->menu==''?'':$r->menu.''.$r->unidmenu);
                $sheet->setCellValue('AA'.$c, $r->bs);
                $sheet->setCellValue('AB'.$c, $r->bs2);
                $sheet->setCellValue('AC'.$c, $r->pago=='CONTADO'?'si':'no');
                $sheet->setCellValue('AD'.$c, $r->Observaciones);
                $sheet->setCellValue('AE'.$c, $r->fact);
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
}
