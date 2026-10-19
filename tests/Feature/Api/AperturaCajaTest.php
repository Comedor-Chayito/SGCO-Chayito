<?php

namespace Tests\Feature\Api;

use App\Models\AperturaCaja;
use App\Models\CierreCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del registro y consulta de apertura de caja y saldo inicial por denominación
 * (US-POS-04 / RF-POS-002 / CC-39).
 *
 * @autor  Equipo SGCO-Chayito
 *
 * @fecha  2026-10-15
 *
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 */
class AperturaCajaTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;

    protected User $mesero;

    /**
     * Configuración inicial antes de cada prueba.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-10-15
     *
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cajero', 'mesero', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');

        $this->mesero = User::factory()->create();
        $this->mesero->assignRole('mesero');

        Sanctum::actingAs($this->cajero);
    }

    /**
     * Valida que un cajero registra la apertura con denominaciones y calcula el saldo inicial exacto.
     */
    public function test_cajero_registra_apertura_con_saldo_inicial_por_denominacion(): void
    {
        $payload = [
            'fecha' => today()->toDateString(),
            'denominaciones' => [
                ['denominacion' => 100, 'tipo' => 'billete', 'cantidad' => 1], // $100.00
                ['denominacion' => 50,  'tipo' => 'billete', 'cantidad' => 2], // $100.00
                ['denominacion' => 20,  'tipo' => 'billete', 'cantidad' => 3], // $60.00
                ['denominacion' => 10,  'tipo' => 'billete', 'cantidad' => 2], // $20.00
                ['denominacion' => 5,   'tipo' => 'billete', 'cantidad' => 2], // $10.00
                ['denominacion' => 1,   'tipo' => 'billete', 'cantidad' => 5], // $5.00
                ['denominacion' => 0.25,'tipo' => 'moneda',  'cantidad' => 10], // $2.50
                ['denominacion' => 0.10,'tipo' => 'moneda',  'cantidad' => 10], // $1.00
                ['denominacion' => 0.05,'tipo' => 'moneda',  'cantidad' => 8], // $0.40
                ['denominacion' => 0.01,'tipo' => 'moneda',  'cantidad' => 10], // $0.10
            ],
            'observaciones' => 'Apertura con fondo de cambio completo',
        ];

        // Total esperado: 100 + 100 + 60 + 20 + 10 + 5 + 2.50 + 1.00 + 0.40 + 0.10 = 299.00
        $response = $this->postJson('/api/aperturas-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Apertura de caja registrada correctamente.',
                'data'    => [
                    'usuario_id'    => $this->cajero->id,
                    'fecha'         => today()->toDateString(),
                    'saldo_inicial' => '299.00',
                    'observaciones' => 'Apertura con fondo de cambio completo',
                ],
            ]);

        $this->assertDatabaseHas('aperturas_caja', [
            'usuario_id'    => $this->cajero->id,
            'fecha'         => today()->toDateString(),
            'saldo_inicial' => 299.00,
        ]);

        $this->assertDatabaseHas('auditorias', [
            'usuario_id'   => $this->cajero->id,
            'accion'       => 'apertura_caja',
            'entidad_type' => AperturaCaja::class,
        ]);
    }

    /**
     * Valida que no se permite registrar una segunda apertura en la misma fecha.
     */
    public function test_no_permite_duplicar_apertura_en_la_misma_fecha(): void
    {
        AperturaCaja::create([
            'usuario_id'    => $this->cajero->id,
            'fecha'         => today()->toDateString(),
            'saldo_inicial' => 100.00,
            'denominaciones'=> [
                ['denominacion' => 50, 'tipo' => 'billete', 'cantidad' => 2, 'subtotal' => 100.00],
            ],
        ]);

        $payload = [
            'fecha' => today()->toDateString(),
            'denominaciones' => [
                ['denominacion' => 20, 'tipo' => 'billete', 'cantidad' => 5],
            ],
        ];

        $response = $this->postJson('/api/aperturas-caja', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);
    }

    /**
     * Valida que no se permite registrar apertura si la caja de hoy ya fue cerrada.
     */
    public function test_no_permite_apertura_si_la_caja_del_dia_ya_fue_cerrada(): void
    {
        CierreCaja::create([
            'usuario_id'                 => $this->cajero->id,
            'fecha'                      => today()->toDateString(),
            'saldo_inicial'              => 50.00,
            'total_ventas_efectivo'      => 100.00,
            'total_ventas_transferencia' => 0.00,
            'total_retiros'              => 20.00,
            'saldo_final'                => 130.00,
            'venta_del_dia'              => 100.00,
            'diferencia'                 => 0.00,
        ]);

        $payload = [
            'fecha' => today()->toDateString(),
            'denominaciones' => [
                ['denominacion' => 50, 'tipo' => 'billete', 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/aperturas-caja', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['caja']);
    }

    /**
     * Valida que el cajero puede consultar la apertura del turno actual.
     */
    public function test_cajero_obtiene_apertura_del_turno_actual(): void
    {
        $apertura = AperturaCaja::create([
            'usuario_id'    => $this->cajero->id,
            'fecha'         => today()->toDateString(),
            'saldo_inicial' => 150.00,
            'denominaciones'=> [
                ['denominacion' => 50, 'tipo' => 'billete', 'cantidad' => 3, 'subtotal' => 150.00],
            ],
            'observaciones' => 'Turno mañana',
        ]);

        $response = $this->getJson('/api/aperturas-caja/turno');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Apertura de caja obtenida.',
                'data'    => [
                    'id'            => $apertura->id,
                    'saldo_inicial' => '150.00',
                    'observaciones' => 'Turno mañana',
                    'caja_cerrada'  => false,
                ],
            ]);
    }

    /**
     * Valida que la apertura consultada indica que la caja ya se cerró cuando existe
     * el cierre del día, para que el panel no muestre "Caja Abierta".
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_apertura_indica_caja_cerrada_tras_el_cierre(): void
    {
        $hoy = today()->toDateString();

        AperturaCaja::create([
            'usuario_id'     => $this->cajero->id,
            'fecha'          => $hoy,
            'saldo_inicial'  => 50.00,
            'denominaciones' => [
                ['denominacion' => 50, 'tipo' => 'billete', 'cantidad' => 1, 'subtotal' => 50.00],
            ],
        ]);

        $this->getJson('/api/aperturas-caja/turno')
            ->assertStatus(200)
            ->assertJsonPath('data.caja_cerrada', false);

        CierreCaja::create([
            'usuario_id'            => $this->cajero->id,
            'fecha'                 => $hoy,
            'saldo_inicial'         => 50.00,
            'total_ventas_efectivo' => 0.00,
            'total_retiros'         => 0.00,
            'saldo_final'           => 50.00,
            'venta_del_dia'         => 0.00,
            'diferencia'            => 0.00,
        ]);

        $this->getJson('/api/aperturas-caja/turno')
            ->assertStatus(200)
            ->assertJsonPath('data.caja_cerrada', true);
    }

    /**
     * Valida que roles sin permiso de caja (ej. mesero) reciben 403 Forbidden.
     */
    public function test_mesero_no_puede_registrar_apertura_de_caja(): void
    {
        Sanctum::actingAs($this->mesero);

        $payload = [
            'denominaciones' => [
                ['denominacion' => 10, 'tipo' => 'billete', 'cantidad' => 1],
            ],
        ];

        $response = $this->postJson('/api/aperturas-caja', $payload);

        $response->assertStatus(403);
    }
}
