<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFromTbTables extends Command
{
    protected $signature = 'legacy:migrate-tb
        {--dry-run : Simula sin guardar}
        {--truncate : Vacia ventas y venta_detalles antes de migrar}
        {--default-user-id=1 : user_id para ventas migradas}
        {--default-agencia=Challgua : agencia para ventas migradas}
        {--chunk=1000 : tamanio de lote}
        {--from-comanda= : comanda inicial}
        {--to-comanda= : comanda final}';

    protected $description = 'Migra tbclientes, tbproductos, tbfactura y tbventas a clientes/productos/ventas/venta_detalles (misma BD).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $truncate = (bool) $this->option('truncate');
        $chunk = max((int) $this->option('chunk'), 100);
        $defaultUserId = (int) $this->option('default-user-id');
        $defaultAgencia = (string) $this->option('default-agencia');

        $requiredTables = ['tbclientes', 'tbproductos', 'tbfactura', 'tbventas', 'clientes', 'productos', 'ventas', 'venta_detalles'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("No existe la tabla '{$table}'.");
                return self::FAILURE;
            }
        }

        if (!DB::table('users')->where('id', $defaultUserId)->exists()) {
            $this->error("No existe users.id = {$defaultUserId}. Usa --default-user-id con un usuario valido.");
            return self::FAILURE;
        }

        $stats = [
            'clientes' => 0,
            'productos' => 0,
            'ventas_desde_factura' => 0,
            'ventas_desde_tbventas' => 0,
            'detalles' => 0,
            'productos_creados_desde_detalle' => 0,
        ];

        if ($dryRun) {
            $this->warn('DRY-RUN activo: no se escribira en la base.');
        }

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            if ($truncate) {
                $this->warn('Aplicando truncate en tablas destino de ventas...');
                if (!$dryRun) {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    DB::table('venta_detalles')->truncate();
                    DB::table('ventas')->truncate();
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            }

            $this->migrateClientes($chunk, $stats, $dryRun);
            $this->migrateProductos($chunk, $stats, $dryRun);
            $this->migrateVentasDesdeFactura($chunk, $defaultUserId, $defaultAgencia, $stats, $dryRun);
            $this->migrateVentasDesdeTbventas($chunk, $defaultUserId, $defaultAgencia, $stats, $dryRun);
            $this->migrateVentaDetalles($chunk, $stats, $dryRun);

            if (!$dryRun) {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $this->error('Error de migracion: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Paso', 'Cantidad'], [
            ['Clientes migrados', $stats['clientes']],
            ['Productos migrados', $stats['productos']],
            ['Ventas desde tbfactura', $stats['ventas_desde_factura']],
            ['Ventas desde tbventas (sin factura)', $stats['ventas_desde_tbventas']],
            ['Detalles migrados', $stats['detalles']],
            ['Productos creados desde detalle', $stats['productos_creados_desde_detalle']],
        ]);

        $this->info('Proceso finalizado.');
        return self::SUCCESS;
    }

    private function migrateClientes(int $chunk, array &$stats, bool $dryRun): void
    {
        $this->line('1) Migrando tbclientes -> clientes');
        $last = 0;
        while (true) {
            $rows = DB::table('tbclientes')
                ->where('Cod_Aut', '>', $last)
                ->orderBy('Cod_Aut')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $r) {
                $payload = [
                    'cod_aut' => (string) $r->Cod_Aut,
                    'id_externo' => $this->s($r->Id),
                    'ci' => $this->s($r->Id),
                    'nombre' => $this->s($r->Nombres),
                    'telefono' => $this->s($r->Telf),
                    'direccion' => $this->s($r->Direccion),
                    'cod_ciudad' => $this->s($r->Cod_ciudad),
                    'cod_nacio' => $this->s($r->Cod_Nacio),
                    'cod_car' => $this->i($r->cod_car),
                    'est_civ' => $this->s($r->EstCiv),
                    'edad' => $this->s($r->edad),
                    'empresa' => $this->s($r->Empresa),
                    'categoria' => $this->i($r->Categoria),
                    'imp_pieza' => $this->f($r->Imp_pieza),
                    'ci_vend' => $this->s($r->CiVend),
                    'list_blanck' => $this->b($r->ListBlanck),
                    'motivo_list_black' => $this->s($r->MotivoListBlack),
                    'list_black' => $this->b($r->ListBlack),
                    'tipo_paciente' => $this->s($r->TipoPaciente),
                    'supra_canal' => $this->s($r->SupraCanal),
                    'canal' => $this->s($r->Canal),
                    'subcanal' => $this->s($r->subcanal),
                    'zona' => $this->s($r->zona),
                    'latitud' => $this->f($r->Latitud),
                    'longitud' => $this->f($r->longitud),
                    'transporte' => $this->s($r->transporte),
                    'territorio' => $this->s($r->territorio),
                    'codcli' => $this->i($r->codcli),
                    'clinew' => $this->s($r->clinew),
                    'venta_estado' => $this->s($r->venta) ?: 'ACTIVO',
                    'complto' => $this->s($r->complto),
                    'tipodocu' => $this->i($r->Tipodocu),
                    'lu' => $this->b($r->lu),
                    'ma' => $this->b($r->Ma),
                    'mi' => $this->b($r->Mi),
                    'ju' => $this->b($r->Ju),
                    'vi' => $this->b($r->Vi),
                    'sa' => $this->b($r->Sa),
                    'do' => $this->b($r->do),
                    'correcli' => $this->s($r->Correcli),
                    'baja' => $this->b($r->baja),
                    'canmayni' => $this->b($r->canmayni),
                    'profecion' => $this->s($r->profecion),
                    'waths' => $this->b($r->waths),
                    'ctas_activo' => $this->b($r->ctasActivo),
                    'ctas_mont' => $this->f($r->ctasMont),
                    'ctas_dias' => $this->i($r->ctasdias),
                    'sexo' => $this->s($r->sexo),
                    'noesempre' => $this->b($r->noesempre),
                    'tarjeta' => $this->s($r->tarjeta),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (!$dryRun) {
                    DB::table('clientes')->updateOrInsert(
                        ['cod_aut' => (string) $r->Cod_Aut],
                        $payload
                    );
                }
                $stats['clientes']++;
                $last = (int) $r->Cod_Aut;
            }
        }
    }

    private function migrateProductos(int $chunk, array &$stats, bool $dryRun): void
    {
        $this->line('2) Migrando tbproductos -> productos');
        $last = 0;
        while (true) {
            $rows = DB::table('tbproductos')
                ->where('CodAut', '>', $last)
                ->orderBy('CodAut')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $r) {
                $p1 = $this->f($r->Precio) ?? 0;
                $p3 = $this->f($r->Precio3);
                $p4 = $this->f($r->Precio4);
                $p5 = $this->f($r->Precio5);
                $p6 = $this->f($r->Precio6);
                $p7 = $this->f($r->Precio7);
                $p8 = $this->f($r->Precio8);
                $p9 = $this->f($r->Precio9);
                $p10 = $this->f($r->Precio10);
                $p11 = $this->f($r->Precio11);
                $p12 = $this->f($r->Precio12);
                $p13 = $this->f($r->Precio13);

                $payload = [
                    'codaut_legacy' => (int) $r->CodAut,
                    'codigo' => $this->s($r->cod_prod) ?: ('LEG-'.$r->CodAut),
                    'nombre' => $this->s($r->Producto) ?: $this->s($r->Nomcomer) ?: ('PRODUCTO '.$r->CodAut),
                    'tipo_producto' => strtoupper(substr($this->s($r->TipPro) ?: 'PF', 0, 3)),
                    'precio1' => $p1,
                    'precio2' => $p3 ?? $p1,
                    'precio3' => $p4 ?? $p1,
                    'precio4' => $p5 ?? $p1,
                    'precio5' => $p6 ?? $p1,
                    'precio6' => $p7 ?? $p1,
                    'precio7' => $p8 ?? $p1,
                    'precio8' => $p9 ?? $p1,
                    'precio9' => $p10 ?? $p1,
                    'precio10' => $p11 ?? $p1,
                    'precio11' => $p12 ?? $p1,
                    'precio12' => $p13 ?? $p1,
                    'precio13' => $p13 ?? $p1,
                    'codigo_unidad' => $this->s($r->codUnid) ?: 'U',
                    'unidades_caja' => $this->f($r->UnidCja),
                    'cantidad_presentacion' => $this->f($r->CantPren) ?: 1,
                    'tipo' => $this->s($r->tipo) ?: 'NORMAL',
                    'oferta' => $this->s($r->oferta) ?: ' ',
                    'codigo_producto_sin' => $this->s($r->codProdSin),
                    'presentacion' => $this->s($r->pqsiramento),
                    'codigo_grupo_sin' => $this->s($r->codgruppasin),
                    'credito' => $this->f($r->credit) ?: 0,
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (!$dryRun) {
                    DB::table('productos')->updateOrInsert(
                        ['codaut_legacy' => (int) $r->CodAut],
                        $payload
                    );
                }
                $stats['productos']++;
                $last = (int) $r->CodAut;
            }
        }
    }

    private function migrateVentasDesdeFactura(
        int $chunk,
        int $defaultUserId,
        string $defaultAgencia,
        array &$stats,
        bool $dryRun
    ): void {
        $this->line('3) Migrando tbfactura -> ventas');
        $last = 0;
        while (true) {
            $q = DB::table('tbfactura')
                ->where('CodAut', '>', $last)
                ->orderBy('CodAut')
                ->limit($chunk);

            if ($this->option('from-comanda') !== null && $this->option('from-comanda') !== '') {
                $q->where('comanda', '>=', (int) $this->option('from-comanda'));
            }
            if ($this->option('to-comanda') !== null && $this->option('to-comanda') !== '') {
                $q->where('comanda', '<=', (int) $this->option('to-comanda'));
            }

            $rows = $q->get();
            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $r) {
                $clienteId = DB::table('clientes')
                    ->where('id_externo', $this->s($r->IdCli))
                    ->orWhere('ci', $this->s($r->IdCli))
                    ->value('id');

                $fecha = $this->dt($r->FechaFac);
                $payload = [
                    'codaut_factura_legacy' => (int) $r->CodAut,
                    'comanda_legacy' => $this->i($r->comanda),
                    'nrofac_legacy' => $this->i($r->nrofac),
                    'user_id' => $defaultUserId,
                    'cliente_id' => $clienteId,
                    'fecha' => $fecha?->toDateString(),
                    'hora' => $fecha?->format('H:i:s'),
                    'ci' => $this->s($r->Ci) ?: $this->s($r->IdCli),
                    'nombre' => null,
                    'estado' => $this->s($r->ESTADO) ?: 'Activo',
                    'tipo_comprobante' => 'Factura',
                    'total' => $this->f($r->Importe) ?: 0,
                    'tipo_pago' => 'Efectivo',
                    'agencia' => $defaultAgencia,
                    'cuf' => $this->s($r->cuffac),
                    'cufd' => $this->s($r->cufDfac),
                    'leyenda' => null,
                    'online' => $this->b($r->enlinea),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (!$dryRun) {
                    DB::table('ventas')->updateOrInsert(
                        ['codaut_factura_legacy' => (int) $r->CodAut],
                        $payload
                    );
                }
                $stats['ventas_desde_factura']++;
                $last = (int) $r->CodAut;
            }
        }
    }

    private function migrateVentasDesdeTbventas(
        int $chunk,
        int $defaultUserId,
        string $defaultAgencia,
        array &$stats,
        bool $dryRun
    ): void {
        $this->line('4) Creando ventas faltantes desde tbventas (si no existen en tbfactura)');

        $min = DB::table('tbventas')->min('Comanda');
        $max = DB::table('tbventas')->max('Comanda');
        if ($min === null || $max === null) {
            return;
        }

        $from = $this->option('from-comanda') !== null && $this->option('from-comanda') !== ''
            ? (int) $this->option('from-comanda')
            : (int) $min;
        $to = $this->option('to-comanda') !== null && $this->option('to-comanda') !== ''
            ? (int) $this->option('to-comanda')
            : (int) $max;

        for ($start = $from; $start <= $to; $start += $chunk) {
            $end = min($to, $start + $chunk - 1);

            $groups = DB::table('tbventas')
                ->selectRaw('Comanda, MIN(CodAut) as min_codaut, MAX(FechaFac) as fecha_fac, MAX(ci) as ci, MAX(IdCli) as idcli, SUM(Monto) as total')
                ->whereBetween('Comanda', [$start, $end])
                ->groupBy('Comanda')
                ->get();

            foreach ($groups as $g) {
                $exists = DB::table('ventas')->where('comanda_legacy', (int) $g->Comanda)->exists();
                if ($exists) {
                    continue;
                }

                $clienteId = DB::table('clientes')
                    ->where('id_externo', $this->s($g->idcli))
                    ->orWhere('ci', $this->s($g->idcli))
                    ->value('id');

                $fecha = $this->dt($g->fecha_fac);
                $payload = [
                    'codaut_factura_legacy' => null,
                    'comanda_legacy' => (int) $g->Comanda,
                    'nrofac_legacy' => null,
                    'user_id' => $defaultUserId,
                    'cliente_id' => $clienteId,
                    'fecha' => $fecha?->toDateString(),
                    'hora' => $fecha?->format('H:i:s'),
                    'ci' => $this->s($g->ci) ?: $this->s($g->idcli),
                    'nombre' => null,
                    'estado' => 'LEGACY',
                    'tipo_comprobante' => 'Venta',
                    'total' => $this->f($g->total) ?: 0,
                    'tipo_pago' => 'Efectivo',
                    'agencia' => $defaultAgencia,
                    'cuf' => null,
                    'cufd' => null,
                    'leyenda' => null,
                    'online' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];

                if (!$dryRun) {
                    DB::table('ventas')->insert($payload);
                }
                $stats['ventas_desde_tbventas']++;
            }
        }
    }

    private function migrateVentaDetalles(int $chunk, array &$stats, bool $dryRun): void
    {
        $this->line('5) Migrando tbventas -> venta_detalles');

        $productoByCodigo = DB::table('productos')->pluck('id', 'codigo')->toArray();
        $last = 0;
        while (true) {
            $q = DB::table('tbventas')
                ->where('CodAut', '>', $last)
                ->orderBy('CodAut')
                ->limit($chunk);

            if ($this->option('from-comanda') !== null && $this->option('from-comanda') !== '') {
                $q->where('Comanda', '>=', (int) $this->option('from-comanda'));
            }
            if ($this->option('to-comanda') !== null && $this->option('to-comanda') !== '') {
                $q->where('Comanda', '<=', (int) $this->option('to-comanda'));
            }

            $rows = $q->get();
            if ($rows->isEmpty()) {
                break;
            }

            $comandas = $rows->pluck('Comanda')->unique()->map(fn ($v) => (int) $v)->values()->all();
            $ventaByComanda = DB::table('ventas')
                ->whereIn('comanda_legacy', $comandas)
                ->pluck('id', 'comanda_legacy')
                ->toArray();

            $codigos = $rows->pluck('cod_pro')
                ->map(fn ($v) => $this->s($v) ?: '')
                ->unique()
                ->values()
                ->all();

            $codigosFaltantes = [];
            foreach ($codigos as $codigo) {
                if ($codigo === '' || isset($productoByCodigo[$codigo])) {
                    continue;
                }
                $codigosFaltantes[] = $codigo;
            }

            if (!empty($codigosFaltantes) && !$dryRun) {
                $insert = [];
                foreach ($codigosFaltantes as $codigo) {
                    $insert[] = [
                        'codigo' => $codigo,
                        'nombre' => 'PRODUCTO LEGACY '.$codigo,
                        'tipo_producto' => 'PF',
                        'precio1' => 0,
                        'precio2' => 0,
                        'precio3' => 0,
                        'precio4' => 0,
                        'precio5' => 0,
                        'precio6' => 0,
                        'precio7' => 0,
                        'precio8' => 0,
                        'precio9' => 0,
                        'precio10' => 0,
                        'precio11' => 0,
                        'precio12' => 0,
                        'precio13' => 0,
                        'codigo_unidad' => 'U',
                        'cantidad_presentacion' => 1,
                        'tipo' => 'NORMAL',
                        'oferta' => ' ',
                        'credito' => 0,
                        'active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }
                if (!empty($insert)) {
                    DB::table('productos')->insertOrIgnore($insert);
                }
                $refresh = DB::table('productos')->whereIn('codigo', $codigosFaltantes)->pluck('id', 'codigo')->toArray();
                foreach ($refresh as $k => $v) {
                    $productoByCodigo[$k] = (int) $v;
                }
                $stats['productos_creados_desde_detalle'] += count($codigosFaltantes);
            } elseif (!empty($codigosFaltantes) && $dryRun) {
                $stats['productos_creados_desde_detalle'] += count($codigosFaltantes);
                foreach ($codigosFaltantes as $codigo) {
                    $productoByCodigo[$codigo] = 0;
                }
            }

            $productoIds = collect($productoByCodigo)->values()->filter(fn ($v) => (int) $v > 0)->unique()->values()->all();
            $productoNombreById = empty($productoIds)
                ? []
                : DB::table('productos')->whereIn('id', $productoIds)->pluck('nombre', 'id')->toArray();

            $payloads = [];
            foreach ($rows as $r) {
                $ventaId = $ventaByComanda[(int) $r->Comanda] ?? null;
                if (!$ventaId) {
                    $last = (int) $r->CodAut;
                    continue;
                }

                $codigo = $this->s($r->cod_pro);
                $productoId = (int) ($productoByCodigo[$codigo] ?? 0);

                if (!$dryRun) {
                    if ($productoId <= 0) {
                        $last = (int) $r->CodAut;
                        continue;
                    }
                    $payloads[] = [
                        'codaut_legacy' => (int) $r->CodAut,
                        'venta_id' => (int) $ventaId,
                        'producto_id' => $productoId,
                        'compra_detalle_id' => null,
                        'nombre' => $productoNombreById[$productoId] ?? null,
                        'cantidad' => $this->f($r->Cant) ?: 0,
                        'unidad' => null,
                        'lote' => $this->s($r->NroLote),
                        'fecha_vencimiento' => null,
                        'precio' => $this->f($r->PVentUnit) ?: 0,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];
                }

                $stats['detalles']++;
                $last = (int) $r->CodAut;
            }

            if (!$dryRun && !empty($payloads)) {
                DB::table('venta_detalles')->upsert(
                    $payloads,
                    ['codaut_legacy'],
                    ['venta_id', 'producto_id', 'compra_detalle_id', 'nombre', 'cantidad', 'unidad', 'lote', 'fecha_vencimiento', 'precio', 'updated_at']
                );
            }
        }
    }

    private function s(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }

    private function i(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (int) $v;
    }

    private function f(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        return (float) str_replace(',', '.', (string) $v);
    }

    private function b(mixed $v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }
        $s = strtoupper(trim((string) $v));
        return in_array($s, ['1', 'S', 'SI', 'Y', 'YES', 'TRUE', 'ON', 'VALIDA'], true);
    }

    private function dt(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        $raw = trim((string) $v);
        if ($raw === '' || $raw === '0') {
            return null;
        }
        if (str_starts_with($raw, '0000-00-00') || str_starts_with($raw, '00/00/0000')) {
            return null;
        }
        try {
            $d = Carbon::parse($raw);
            if ((int) $d->format('Y') < 1900) {
                return null;
            }
            return $d;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
