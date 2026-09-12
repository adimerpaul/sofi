<?php

namespace Tests\Feature;

use App\Http\Controllers\CreditoController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Base aislada: nunca migrar ni limpiar los datos comerciales para probar.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('tbclientes', function (Blueprint $t) {
            $t->integer('Cod_Aut')->primary(); $t->string('Id'); $t->string('Nombres');
        });
        Schema::create('facturas', function (Blueprint $t) {
            $t->integer('id')->primary(); $t->integer('cliente_id')->nullable(); $t->string('nombre');
            $t->date('fecha'); $t->decimal('total', 12, 2); $t->string('tipo_pago'); $t->string('estado'); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('tbctascobrar', function (Blueprint $t) {
            $t->integer('comanda'); $t->string('CINIT'); $t->integer('Nrocierre');
            $t->decimal('Importe', 12, 2); $t->decimal('Acuenta', 12, 2); $t->date('FechaEntreg');
        });
        Schema::create('tbctascow', function (Blueprint $t) {
            $t->integer('comanda'); $t->string('idCli'); $t->integer('procesado'); $t->decimal('pago', 12, 2);
        });
        require_once database_path('migrations/2026_09_12_000004_create_creditos_tables.php');
        (new \CreateCreditosTables())->up();
        DB::table('tbclientes')->insert(['Cod_Aut' => 1, 'Id' => '123', 'Nombres' => 'Cliente prueba']);
        DB::table('facturas')->insert(['id' => 1, 'cliente_id' => 1, 'nombre' => 'Cliente prueba', 'fecha' => date('Y-m-d'), 'total' => 100, 'tipo_pago' => 'CRÉDITO', 'estado' => 'ACTIVO']);
    }

    private function peticion(array $datos): Request
    {
        $request = Request::create('/', 'POST', $datos);
        $request->setUserResolver(function () { return (new User())->forceFill(['CodAut' => 7]); });
        return $request;
    }

    public function test_credito_aparece_y_abono_parcial_actualiza_saldo_sin_duplicar_reintento()
    {
        $controller = new CreditoController();
        $this->assertEquals(100, $controller->index($this->peticion([]))['saldo']);
        $pago = $this->peticion(['monto' => '30.25', 'forma_pago' => 'EFECTIVO', 'solicitud_id' => (string) Str::uuid()]);
        $controller->abonar($pago, 'factura', 1);
        $controller->abonar($pago, 'factura', 1);
        $this->assertEquals(1, DB::table('creditos_abonos')->count());
        $this->assertEquals(69.75, $controller->index($this->peticion([]))['saldo']);
        $controller->abonar($this->peticion(['monto' => '69.75', 'forma_pago' => 'QR', 'solicitud_id' => (string) Str::uuid()]), 'factura', 1);
        $this->assertEquals(0, $controller->index($this->peticion([]))['saldo']);
    }

    public function test_no_permite_cobrar_mas_del_saldo()
    {
        try {
            (new CreditoController())->abonar($this->peticion(['monto' => '100.01', 'forma_pago' => 'QR', 'solicitud_id' => (string) Str::uuid()]), 'factura', 1);
            $this->fail('Debió rechazar el sobrepago');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('monto', $e->errors());
            $this->assertEquals(0, DB::table('creditos_abonos')->count());
        }
    }

    public function test_deuda_manual_y_su_abono()
    {
        $controller = new CreditoController();
        $request = $this->peticion(['cliente_id' => 1, 'fecha' => date('Y-m-d'), 'concepto' => 'Saldo inicial', 'monto' => '50.00', 'solicitud_id' => (string) Str::uuid()]);
        $id = $controller->store($request)->getData()->id;
        $controller->store($request);
        $this->assertEquals(1, DB::table('creditos_manuales')->count());
        $controller->abonar($this->peticion(['monto' => '10.00', 'forma_pago' => 'TRANSFERENCIA', 'solicitud_id' => (string) Str::uuid()]), 'manual', $id);
        $this->assertEquals(140, $controller->index($this->peticion(['cliente_id' => 1]))['saldo']);
    }

    public function test_no_cobra_venta_anulada_y_conserva_abonos_para_revision()
    {
        $controller = new CreditoController();
        $controller->abonar($this->peticion(['monto' => '10', 'forma_pago' => 'EFECTIVO', 'solicitud_id' => (string) Str::uuid()]), 'factura', 1);
        DB::table('facturas')->where('id', 1)->update(['estado' => 'ANULADO']);
        $fila = $controller->index($this->peticion([]))['deudas'][0];
        $this->assertEquals(10, $fila->pagado);
        $this->assertEquals('ANULADA CON ABONOS: REVISAR', $fila->estado);
        $this->expectException(ValidationException::class);
        $controller->abonar($this->peticion(['monto' => '10', 'forma_pago' => 'EFECTIVO', 'solicitud_id' => (string) Str::uuid()]), 'factura', 1);
    }

    public function test_cuenta_antigua_mantiene_saldo_y_cobros_por_conciliar_separados()
    {
        DB::table('tbctascobrar')->insert([
            ['comanda' => 80, 'CINIT' => '123', 'Nrocierre' => 0, 'Importe' => 70, 'Acuenta' => 0, 'FechaEntreg' => date('Y-m-d')],
            ['comanda' => 80, 'CINIT' => '123', 'Nrocierre' => 1, 'Importe' => 0, 'Acuenta' => 20, 'FechaEntreg' => date('Y-m-d')],
        ]);
        DB::table('tbctascow')->insert(['comanda' => 80, 'idCli' => '123', 'procesado' => 0, 'pago' => 5]);
        $resultado = (new CreditoController())->index($this->peticion([]));
        $this->assertEquals(150, $resultado['saldo']);
        $caja = $resultado['deudas']->firstWhere('origen', 'caja');
        $this->assertEquals(50, $caja->saldo);
        $this->assertEquals(5, $caja->por_conciliar);
    }
}
