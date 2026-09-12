<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['auth:sanctum', 'permission:cobranzasrecojo'])
            ->get('/prueba-permiso-cobrar', function () { return response()->json(['ok' => true]); });
    }

    public function test_usuario_con_permiso_puede_acceder_por_http()
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->shouldReceive('canAny')->once()->with(['cobranzasrecojo'])->andReturn(true);
        $this->actingAs($usuario, 'sanctum')->getJson('/prueba-permiso-cobrar')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_usuario_sin_permiso_recibe_403_en_lugar_de_error_500()
    {
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->shouldReceive('canAny')->once()->with(['cobranzasrecojo'])->andReturn(false);
        $this->actingAs($usuario, 'sanctum')->getJson('/prueba-permiso-cobrar')->assertForbidden();
    }

    public function test_visitante_necesita_autenticarse()
    {
        $this->getJson('/prueba-permiso-cobrar')->assertUnauthorized();
    }
}
