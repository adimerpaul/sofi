<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

class FacturaController extends Controller{
    function generarPDF($comanda, Request $request){
        $user = $request->user();
        error_log(json_encode($user));
        $pedido = DB::table('tbventas as v')
            ->join('tbproductos as p', 'v.cod_pro', '=', 'p.cod_prod')
            ->where('v.Comanda', $comanda)
            ->select('p.cod_prod', 'p.Producto', 'v.cant', 'v.PVentUnit', 'v.Monto')
            ->get();

        $cliente = DB::table('tbctascobrar as p')
            ->join('tbclientes as c', 'p.CINIT', '=', 'c.Id')
            ->where('p.comanda', $comanda)
            ->select('c.Nombres', 'c.Direccion', 'p.FechaEntreg')
            ->first();

        $total = $pedido->sum(fn($item) => $item->cant * $item->PVentUnit);

        $formatter = new NumeroALetras();
        $literal = $formatter->toMoney($total, 2, 'Bs', 'centavos');

        $pdf = Pdf::loadView('pdf.factura', compact('pedido', 'cliente', 'comanda', 'total', 'literal', 'user'))
            ->setPaper('A4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);
        return $pdf->stream("factura_{$comanda}.pdf", ['Attachment' => false]);
    }
}
