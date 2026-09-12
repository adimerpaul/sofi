<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditoController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cobranzasrecojo');
    }

    public function clientes(Request $request)
    {
        $datos = $request->validate(['buscar' => 'required|string|min:2|max:100']);
        return DB::table('tbclientes')->where(function ($q) use ($datos) {
            $q->where('Nombres', 'like', '%'.$datos['buscar'].'%')
                ->orWhere('Id', 'like', '%'.$datos['buscar'].'%');
        })->orderBy('Nombres')->limit(30)->get(['Cod_Aut as id', 'Nombres as nombre', 'Id as nit']);
    }

    public function index(Request $request)
    {
        $datos = $request->validate(['cliente_id' => 'nullable|integer|exists:tbclientes,Cod_Aut']);
        $cliente = $datos['cliente_id'] ?? null;
        $abonos = DB::table('creditos_abonos')->select('origen', 'deuda_id', DB::raw('SUM(monto) as pagado'))
            ->groupBy('origen', 'deuda_id')->get()->keyBy(function ($a) { return $a->origen.':'.$a->deuda_id; });

        $facturas = DB::table('facturas as f')->leftJoin('tbclientes as c', 'c.Cod_Aut', '=', 'f.cliente_id')
            ->whereIn('f.tipo_pago', ['CRÉDITO', 'CREDITO'])
            ->when($cliente, function ($q) use ($cliente) { $q->where('f.cliente_id', $cliente); })
            ->get(['f.id', 'f.cliente_id', 'f.fecha', 'f.total as monto', 'f.estado', 'f.deleted_at',
                DB::raw("COALESCE(c.Nombres, f.nombre) as cliente"), DB::raw("CONCAT('Venta #', f.id) as concepto")]);
        $manuales = DB::table('creditos_manuales as d')->join('tbclientes as c', 'c.Cod_Aut', '=', 'd.cliente_id')
            ->when($cliente, function ($q) use ($cliente) { $q->where('d.cliente_id', $cliente); })
            ->get(['d.id', 'd.cliente_id', 'd.fecha', 'd.monto', 'd.concepto', 'c.Nombres as cliente']);
        $filas = collect();
        foreach (['factura' => $facturas, 'manual' => $manuales] as $origen => $deudas) {
            foreach ($deudas as $deuda) {
                $deuda->origen = $origen;
                $deuda->clave = $origen.':'.$deuda->id;
                $deuda->pagado = (float) ($abonos->get($deuda->clave)->pagado ?? 0);
                $deuda->monto = (float) $deuda->monto;
                $activa = $origen === 'manual' || ($deuda->estado === 'ACTIVO' && !$deuda->deleted_at);
                if (!$activa && !$deuda->pagado) { continue; }
                $deuda->saldo = $activa ? max(0, round($deuda->monto - $deuda->pagado, 2)) : 0;
                $deuda->estado = !$activa ? 'ANULADA CON ABONOS: REVISAR' : ($deuda->saldo > 0 ? 'PENDIENTE' : 'PAGADO');
                $filas->push($deuda);
            }
        }

        // Las cuentas de caja conservan su fuente y su circuito de conciliacion.
        $legadas = DB::table('tbctascobrar as d')->join('tbclientes as c', 'c.Id', '=', 'd.CINIT')
            ->where('d.Nrocierre', 0)->where('d.Acuenta', 0)
            ->when($cliente, function ($q) use ($cliente) { $q->where('c.Cod_Aut', $cliente); })
            ->select('d.comanda as id', 'c.Cod_Aut as cliente_id', 'c.Nombres as cliente')
            ->selectRaw('MIN(d.FechaEntreg) as fecha, SUM(d.Importe) as monto, d.CINIT')
            ->groupBy('d.comanda', 'd.CINIT', 'c.Cod_Aut', 'c.Nombres')->get();
        $pagosCaja = DB::table('tbctascobrar')->select('comanda', 'CINIT')->selectRaw('SUM(Acuenta) as pagado')
            ->groupBy('comanda', 'CINIT')->get()->keyBy(function ($p) { return trim($p->CINIT).':'.$p->comanda; });
        $pendientes = DB::table('tbctascow')->where('procesado', 0)->select('comanda', 'idCli')->selectRaw('SUM(pago) as pagado')
            ->groupBy('comanda', 'idCli')->get()->keyBy(function ($p) { return trim($p->idCli).':'.$p->comanda; });
        foreach ($legadas as $d) {
            $key = trim($d->CINIT).':'.$d->id;
            $d->origen = 'caja';
            $d->clave = 'caja:'.$key;
            $d->concepto = 'Nota de caja #'.$d->id;
            $d->monto = (float) $d->monto;
            $d->pagado = (float) ($pagosCaja->get($key)->pagado ?? 0);
            $d->por_conciliar = (float) ($pendientes->get($key)->pagado ?? 0);
            $d->saldo = max(0, round($d->monto - $d->pagado, 2));
            $d->estado = 'CAJA';
            if ($d->saldo > 0) { $filas->push($d); }
        }
        return ['deudas' => $filas->sortByDesc('fecha')->values(), 'saldo' => round($filas->sum('saldo'), 2)];
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => 'required|integer|exists:tbclientes,Cod_Aut',
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            'concepto' => 'required|string|max:255',
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'solicitud_id' => 'required|uuid',
        ]);
        return DB::transaction(function () use ($datos, $request) {
            DB::table('tbclientes')->where('Cod_Aut', $datos['cliente_id'])->lockForUpdate()->first();
            $previo = DB::table('creditos_manuales')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless((int) $previo->cliente_id === (int) $datos['cliente_id']
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (string) $previo->concepto === $datos['concepto']
                    && (float) $previo->monto === (float) $datos['monto'], 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            $id = DB::table('creditos_manuales')->insertGetId($datos + [
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $id], 201);
        });
    }

    public function historial($origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        return DB::table('creditos_abonos as a')->leftJoin('personal as p', 'p.CodAut', '=', 'a.user_id')
            ->where('a.origen', $origen)->where('a.deuda_id', $id)->orderByDesc('a.id')
            ->get(['a.id', 'a.monto', 'a.forma_pago', 'a.referencia', 'a.created_at',
                DB::raw("TRIM(CONCAT(COALESCE(p.Nombre1, ''), ' ', COALESCE(p.App1, ''))) as cobrador")]);
    }

    public function abonar(Request $request, $origen, $id)
    {
        abort_unless(in_array($origen, ['factura', 'manual'], true), 404);
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'forma_pago' => 'required|in:EFECTIVO,QR,TRANSFERENCIA',
            'referencia' => 'nullable|string|max:100', 'solicitud_id' => 'required|uuid',
        ]);
        return DB::transaction(function () use ($datos, $request, $origen, $id) {
            $deuda = DB::table($origen === 'factura' ? 'facturas' : 'creditos_manuales')->where('id', $id)->lockForUpdate()->first();
            abort_unless($deuda, 404);
            $previo = DB::table('creditos_abonos')->where('solicitud_id', $datos['solicitud_id'])->first();
            if ($previo) {
                abort_unless($previo->origen === $origen && (int) $previo->deuda_id === (int) $id
                    && (int) $previo->user_id === (int) $request->user()->CodAut
                    && (float) $previo->monto === (float) $datos['monto']
                    && $previo->forma_pago === $datos['forma_pago'], 409,
                    'Esta solicitud ya fue registrada con otros datos. Cierre el formulario y actualice.');
                return response()->json(['id' => $previo->id]);
            }
            if ($origen === 'factura' && ($deuda->estado !== 'ACTIVO' || $deuda->deleted_at || !in_array($deuda->tipo_pago, ['CRÉDITO', 'CREDITO'], true))) {
                throw ValidationException::withMessages(['deuda' => 'Esta venta no tiene un crédito activo.']);
            }
            $monto = $origen === 'factura' ? $deuda->total : $deuda->monto;
            $pagado = DB::table('creditos_abonos')->where('origen', $origen)->where('deuda_id', $id)->sum('monto');
            if ((int) round($datos['monto'] * 100) > (int) round($monto * 100) - (int) round($pagado * 100)) {
                throw ValidationException::withMessages(['monto' => 'El abono supera el saldo pendiente. Actualice la cuenta.']);
            }
            $abono = DB::table('creditos_abonos')->insertGetId($datos + [
                'origen' => $origen, 'deuda_id' => $id, 'cliente_id' => $deuda->cliente_id,
                'user_id' => $request->user()->CodAut, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['id' => $abono, 'saldo' => round($monto - $pagado - $datos['monto'], 2)], 201);
        });
    }
}
