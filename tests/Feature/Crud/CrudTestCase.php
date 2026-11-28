<?php

namespace Tests\Feature\Crud;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Base de los scripts de captura y edición de pantallas CRUD (Cuadrante 1 – CC-177).
 * Siembra roles y permisos y autentica al administrador, que tiene acceso a todos los catálogos.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-27
 *
 * @módulo Plan de pruebas – CC-177 (B3 Cuadrante 1)
 */
abstract class CrudTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $administrador;

    /**
     * Siembra roles y permisos oficiales y abre sesión Sanctum como administrador.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Plan de pruebas – CC-177
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->administrador = User::where('email', 'admin@sgco-chayito.local')->firstOrFail();
        Sanctum::actingAs($this->administrador);
    }

    /**
     * Afirma que la respuesta cumple el contrato de la API (status ok, message y data).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-27
     *
     * @módulo Plan de pruebas – CC-177
     *
     * @param  TestResponse  $respuesta  Respuesta HTTP de la pantalla.
     * @param  int  $codigo  Código HTTP esperado (201 captura, 200 edición).
     * @return TestResponse La misma respuesta, para encadenar afirmaciones.
     */
    protected function assertContratoOk(TestResponse $respuesta, int $codigo): TestResponse
    {
        return $respuesta->assertStatus($codigo)
            ->assertJsonStructure(['status', 'message', 'data'])
            ->assertJsonPath('status', 'ok');
    }
}
