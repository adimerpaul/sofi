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
    public function show($id)
    {
        //
    }

    public function consulta($t,$f1,$f2)
    {
        if ($t=='p'){
            $query= DB::SELECT("SELECT * from tbpedidos p, tbclientes c
            where c.Cod_Aut=p.idCli and date(fecha)>='$f1' and date(fecha)<='$f2'
            and tipo='POLLO' AND estado='ENVIADO' ");
            $t='';
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('ppollo.xlsx');
            $sheet = $spreadsheet->getActiveSheet();
            $c=3;
            foreach ($query as $r){
//                $t.=" ".$r->Nombres;
                $sheet->setCellValue('B'.$c, $r->Nombres);
                $sheet->setCellValue('C'.$c, $r->cbrasa5);
                $sheet->setCellValue('D'.$c, $r->ubrasa5);
                $sheet->setCellValue('E'.$c, $r->bsbrasa5);
                $sheet->setCellValue('F'.$c, $r->obsbrasa5);
                $sheet->setCellValue('G'.$c, $r->cbrasa6);
                $sheet->setCellValue('H'.$c, $r->cubrasa6);
                $sheet->setCellValue('I'.$c, $r->bsbrasa6);
                $sheet->setCellValue('J'.$c, $r->obsbrasa6);
                $c++;
            }
//            return $t;
            //        $spreadsheet = new Spreadsheet();

//            $sheet->setCellValue('A1', 'ID');


// Write an .xlsx file
            $date = date('d-m-y-'.substr((string)microtime(), 1, 8));
            $date = str_replace(".", "", $date);
            $filename = "export_".$date.".xlsx";
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
