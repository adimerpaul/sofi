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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CobroCreditoVendedorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('tbclientes', function (Blueprint $t) {
            $t->integer('Cod_Aut')->primary(); $t->string('Id'); $t->string('Nombres'); $t->string('CiVend');
        });
        Schema::create('facturas', function (Blueprint $t) {
            $t->integer('id')->primary(); $t->integer('cliente_id'); $t->string('nombre');
            $t->date('fecha'); $t->decimal('total', 12, 2); $t->string('tipo_pago'); $t->string('estado');
            $t->timestamp('deleted_at')->nullable(); $t->string('tipo_comprobante')->default('FACTURA'); $t->integer('pedido_nro')->nullable();
        });
        require_once database_path('migrations/2026_09_12_000004_create_creditos_tables.php');
        (new \CreateCreditosTables())->up();
        DB::table('tbclientes')->insert([
            ['Cod_Aut' => 1, 'Id' => '123', 'Nombres' => 'Mi cliente', 'CiVend' => ' V7 '],
            ['Cod_Aut' => 2, 'Id' => '456', 'Nombres' => 'Otro cliente', 'CiVend' => 'V8'],
        ]);
        foreach ([1, 2] as $id) {
            DB::table('facturas')->insert(['id' => $id, 'cliente_id' => $id, 'nombre' => 'Cliente', 'fecha' => date('Y-m-d'), 'total' => 100, 'tipo_pago' => 'CREDITO', 'estado' => 'ACTIVO']);
        }
    }

    private function peticion(array $datos = []): Request
    {
        $request = Request::create('/', 'POST', $datos);
        $request->setUserResolver(function () { return (new User())->forceFill(['CodAut' => 7, 'ci' => 'V7']); });
        return $request;
    }

    private function cobro($id = 1, $monto = '30.25', $origen = 'factura')
    {
        return ['origen' => $origen, 'id' => $id, 'monto' => $monto, 'referencia' => 'B-001', 'solicitud_id' => (string) Str::uuid()];
    }

    public function test_muestra_deudores_de_todos_los_vendedores_y_permite_cobrarlos()
    {
        $controller = new CreditoController();
        $clientes = $controller->clientesVendedor($this->peticion());
        $this->assertCount(2, $clientes);
        $this->assertEquals(1, $clientes[0]->id);
        $this->assertEquals(100, $clientes[0]->saldo);
        $this->assertCount(1, $controller->deudasVendedor($this->peticion(), 2));
        $controller->cobrarVendedor($this->peticion(['cobros' => [$this->cobro(2)]]), 2);
        $this->assertEquals(69.75, $controller->index($this->peticion(['cliente_id' => 2]))['saldo']);
    }

    public function test_guarda_boleta_y_cobrador_sin_duplicar_reintentos()
    {
        $controller = new CreditoController();
        $request = $this->peticion(['cobros' => [$this->cobro()]]);
        $controller->cobrarVendedor($request, 1);
        $controller->cobrarVendedor($request, 1);
        $this->assertEquals(1, DB::table('creditos_abonos')->count());
        $abono = DB::table('creditos_abonos')->first();
        $this->assertEquals('B-001', $abono->referencia);
        $this->assertEquals(7, $abono->user_id);
        $this->assertEquals(69.75, $controller->index($this->peticion(['cliente_id' => 1]))['saldo']);
    }

    public function test_revierte_todo_el_lote_si_un_abono_supera_el_saldo()
    {
        DB::table('creditos_manuales')->insert(['cliente_id' => 1, 'fecha' => date('Y-m-d'), 'concepto' => 'Deuda', 'monto' => 10, 'user_id' => 7, 'solicitud_id' => (string) Str::uuid()]);
        try {
            (new CreditoController())->cobrarVendedor($this->peticion(['cobros' => [$this->cobro(), $this->cobro(1, '11', 'manual')]]), 1);
            $this->fail('Debio rechazar el sobrepago');
        } catch (ValidationException $e) {
            $this->assertEquals(0, DB::table('creditos_abonos')->count());
        }
    }

    public function test_rechaza_deuda_de_otro_cliente_aunque_el_cliente_de_la_url_sea_propio()
    {
        try {
            (new CreditoController())->cobrarVendedor($this->peticion(['cobros' => [$this->cobro(2)]]), 1);
            $this->fail('Debio rechazar deuda ajena');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals(0, DB::table('creditos_abonos')->count());
        }
    }

    public function test_no_permite_cambiar_boleta_de_un_reintento()
    {
        $controller = new CreditoController();
        $cobro = $this->cobro();
        $controller->cobrarVendedor($this->peticion(['cobros' => [$cobro]]), 1);
        $cobro['referencia'] = 'OTRA';
        $this->expectException(HttpException::class);
        $controller->cobrarVendedor($this->peticion(['cobros' => [$cobro]]), 1);
    }

    public function test_cobra_credito_y_deuda_manual_en_un_solo_lote()
    {
        DB::table('creditos_manuales')->insert(['cliente_id' => 1, 'fecha' => date('Y-m-d'), 'concepto' => 'Deuda', 'monto' => 10, 'user_id' => 7, 'solicitud_id' => (string) Str::uuid()]);
        $controller = new CreditoController();
        $controller->cobrarVendedor($this->peticion(['cobros' => [$this->cobro(1, '100'), $this->cobro(1, '10', 'manual')]]), 1);
        $this->assertEquals(2, DB::table('creditos_abonos')->count());
        $restantes = $controller->clientesVendedor($this->peticion());
        $this->assertCount(1, $restantes);
        $this->assertEquals(2, $restantes[0]->id);
    }

    public function test_permiso_de_vendedor_no_da_acceso_a_administrar_creditos()
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->forceFill(['CodAut' => 7, 'ci' => 'V7']);
        $usuario->shouldReceive('canAny')->with(['cobranza'])->andReturn(true);
        $usuario->shouldReceive('canAny')->with(['cobranzasrecojo'])->andReturn(false);
        $this->actingAs($usuario, 'sanctum')->getJson('/api/vendedor/creditos/clientes')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/creditos')->assertForbidden();
    }

    public function test_usuario_sin_permiso_no_puede_cobrar()
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->shouldReceive('canAny')->with(['cobranza'])->andReturn(false);
        $this->actingAs($usuario, 'sanctum')->postJson('/api/vendedor/creditos/clientes/1/cobros', ['cobros' => [$this->cobro()]])->assertForbidden();
        $this->assertEquals(0, DB::table('creditos_abonos')->count());
    }
}
