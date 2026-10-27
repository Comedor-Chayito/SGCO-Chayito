<?php

namespace Tests\Feature\Api;

use App\Models\CierreCaja;
use App\Models\RetiroParcial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del registro y consulta de retiros parciales del turno (US-POS-03 / RF-POS-002 / CC-37).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
 */
class RetiroTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;

    /**
     * Crea los roles necesarios y autentica a un cajero antes de cada prueba.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cajero', 'mesero', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');
        Sanctum::actingAs($this->cajero);
    }

    /**
     * Valida que el cajero registra un retiro, queda a su nombre y deja registro de auditoría.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    public function test_cajero_registra_retiro_con_auditoria(): void
    {
        $response = $this->postJson('/api/retiros', [
            'monto'  => 25.50,
            'motivo' => 'Pago a proveedor',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Retiro registrado correctamente.')
            ->assertJsonPath('data.monto', '25.50')
            ->assertJsonPath('data.motivo', 'Pago a proveedor')
            ->assertJsonPath('data.usuario.id', $this->cajero->id);

        $this->assertDatabaseHas('retiros_parciales', [
            'usuario_id' => $this->cajero->id,
            'monto'      => 25.50,
            'motivo'     => 'Pago a proveedor',
        ]);

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->cajero->id,
            'accion'     => 'retiro_registrado',
            'entidad_id' => $response->json('data.id'),
        ]);
    }

    /**
     * Valida que el administrador también puede registrar retiros (CLAUDE.md §9).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-ADM-008 (CC-38)
     *
     * @return void
     */
    public function test_administrador_puede_registrar_retiro(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Sanctum::actingAs($admin);

        $this->postJson('/api/retiros', [
            'monto'  => 10.00,
            'motivo' => 'Gastos menores',
        ])->assertStatus(201);
    }

    /**
     * Valida que se rechazan montos vacíos, no positivos, con más de 2 decimales o sobre el límite.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    public function test_rechaza_montos_invalidos(): void
    {
        foreach ([null, 0, -5, 'abc', 10.555, 100000.00] as $montoInvalido) {
            $this->postJson('/api/retiros', [
                'monto'  => $montoInvalido,
                'motivo' => 'Gastos menores',
            ])->assertStatus(422)->assertJsonValidationErrors(['monto']);
        }

        $this->assertDatabaseCount('retiros_parciales', 0);
    }

    /**
     * Valida que el motivo es obligatorio, de al menos 3 y máximo 150 caracteres.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    public function test_rechaza_motivos_invalidos(): void
    {
        foreach ([null, '', 'ab', str_repeat('a', 151)] as $motivoInvalido) {
            $this->postJson('/api/retiros', [
                'monto'  => 5.00,
                'motivo' => $motivoInvalido,
            ])->assertStatus(422)->assertJsonValidationErrors(['motivo']);
        }

        $this->assertDatabaseCount('retiros_parciales', 0);
    }

    /**
     * Valida que no se admiten retiros cuando la caja del día ya fue cerrada.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    public function test_rechaza_retiro_si_la_caja_del_dia_ya_fue_cerrada(): void
    {
        CierreCaja::create([
            'usuario_id'    => $this->cajero->id,
            'fecha'         => today()->toDateString(),
            'saldo_inicial' => 50.00,
            'saldo_final'   => 80.00,
            'venta_del_dia' => 30.00,
        ]);

        $this->postJson('/api/retiros', [
            'monto'  => 5.00,
            'motivo' => 'Gastos menores',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['caja']);

        $this->assertDatabaseCount('retiros_parciales', 0);
    }

    /**
     * Valida que el listado del turno solo trae los retiros de hoy del usuario autenticado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
     *
     * @return void
     */
    public function test_listado_del_turno_solo_incluye_mis_retiros_de_hoy(): void
    {
        $otroCajero = User::factory()->create();

        $mioHoy = RetiroParcial::create([
            'usuario_id' => $this->cajero->id,
            'monto'      => 12.00,
            'motivo'     => 'Retiro de hoy',
        ]);

        $mioAyer = RetiroParcial::create([
            'usuario_id' => $this->cajero->id,
            'monto'      => 7.00,
            'motivo'     => 'Retiro de ayer',
        ]);
        $mioAyer->forceFill(['created_at' => now()->subDay()])->save();

        RetiroParcial::create([
            'usuario_id' => $otroCajero->id,
            'monto'      => 3.00,
            'motivo'     => 'Retiro de otro cajero',
        ]);

        $response = $this->getJson('/api/retiros');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mioHoy->id);
    }

    /**
     * Valida que sin sesión no se puede registrar ni listar retiros (RF-ADM-008).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-ADM-008 (CC-38)
     *
     * @return void
     */
    public function test_rechaza_solicitudes_sin_sesion(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/retiros', [
            'monto'  => 5.00,
            'motivo' => 'Gastos menores',
        ])->assertStatus(401);

        $this->getJson('/api/retiros')->assertStatus(401);
    }

    /**
     * Valida que un mesero no puede registrar retiros: es una operación de caja (CLAUDE.md §9).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-14
     * @módulo POS – US-POS-03 / RF-ADM-008 (CC-38)
     *
     * @return void
     */
    public function test_mesero_no_puede_registrar_retiros(): void
    {
        $mesero = User::factory()->create();
        $mesero->assignRole('mesero');
        Sanctum::actingAs($mesero);

        $this->postJson('/api/retiros', [
            'monto'  => 5.00,
            'motivo' => 'Gastos menores',
        ])->assertStatus(403);

        $this->assertDatabaseCount('retiros_parciales', 0);
    }
}
