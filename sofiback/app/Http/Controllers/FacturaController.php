<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Luecano\NumeroALetras\NumeroALetras;

class FacturaController extends Controller{
    function generarPDF($comanda, Request $request){
        $pedido = DB::table('tbventas as v')
            ->join('tbproductos as p', 'v.cod_pro', '=', 'p.cod_prod')
            ->where('v.Comanda', $comanda)
            ->select('p.cod_prod', 'p.Producto', 'v.cant', 'v.PVentUnit', 'v.Monto')
            ->get();

        $cliente = $this->buscarCliente($comanda);

        if (!$cliente) {
            return response()->json([
                'message' => "No se encontro el cliente del pedido $comanda"
            ], 404);
        }

        if ($pedido->isEmpty()) {
            return response()->json([
                'message' => "El pedido $comanda todavia no tiene productos registrados, no se puede imprimir la boleta"
            ], 409);
        }

        $vendedor = $this->buscarVendedor($comanda, $request->user());

        $total = $pedido->sum(fn($item) => $item->cant * $item->PVentUnit);

        $formatter = new NumeroALetras();
        $literal = $formatter->toMoney($total, 2, 'Bs', 'centavos');

        $pdf = Pdf::loadView('pdf.factura', compact('pedido', 'cliente', 'comanda', 'total', 'literal', 'vendedor'))
            ->setPaper('A4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);
        return $pdf->stream("factura_{$comanda}.pdf", ['Attachment' => false]);
    }

    /**
     * El pedido vive en tbctascobrar mientras esta pendiente de cobro; una vez
     * cobrado sale de esa tabla, asi que se lo recupera por la entrega.
     */
    private function buscarCliente($comanda){
        $cliente = DB::table('tbctascobrar as p')
            ->join('tbclientes as c', 'p.CINIT', '=', 'c.Id')
            ->where('p.comanda', $comanda)
            ->select('c.Id', 'c.Nombres', 'c.Telf', 'c.Direccion', 'c.zona', 'p.FechaEntreg')
            ->first();

        if ($cliente) {
            return $cliente;
        }

        return DB::table('entregas as e')
            ->join('tbclientes as c', 'c.Cod_Aut', '=', 'e.cliente_id')
            ->where('e.comanda', $comanda)
            ->select('c.Id', 'c.Nombres', 'c.Telf', 'c.Direccion', 'c.zona', 'e.fechaEntreg as FechaEntreg')
            ->first();
    }

    /** Vendedor del pedido; si no se lo ubica queda el usuario que imprime. */
    private function buscarVendedor($comanda, $user){
        $ci = DB::table('tbctascobrar')->where('comanda', $comanda)->value('CIFunc')
            ?: DB::table('tbventas')->where('Comanda', $comanda)->value('ci');

        $personal = $ci
            ? DB::table('personal')->whereRaw('TRIM(ci)=?', [trim($ci)])->first()
            : null;

        if (!$personal) {
            $personal = $user;
        }

        if (!$personal) {
            return 'No Asignado';
        }

        $nombre = trim(implode(' ', array_filter([
            trim($personal->Nombre1 ?? ''),
            trim($personal->App1 ?? ''),
            trim($personal->Apm ?? ''),
        ])));

        return $nombre !== '' ? $nombre : 'No Asignado';
    }
}
