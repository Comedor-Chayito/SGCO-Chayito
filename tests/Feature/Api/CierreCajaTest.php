<?php

namespace Tests\Feature\Api;

use App\Models\CierreCaja;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\RetiroParcial;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del cierre diario de caja y bitácora inalterable
 * (US-POS-04 / RF-POS-002 / CC-41).
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-09-22
 *
 * @módulo POS – RF-POS-002
 */
class CierreCajaTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;

    protected Mesa $mesa;

    protected Platillo $platillo;

    /**
     * Configuración inicial antes de cada prueba de cierre.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cajero = User::factory()->create(['id' => 1]);

        $this->mesa = Mesa::create([
            'numero' => 1,
            'capacidad' => 4,
            'estado' => 'libre',
        ]);

        $this->platillo = Platillo::create([
            'nombre' => 'Pupusa de Queso',
            'descripcion' => 'Pupusa artesanal de queso',
            'precio_unitario' => 0.75,
            'disponible' => true,
        ]);
    }

    /**
     * Crea ventas y retiros de prueba para un día dado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @param  string  $fecha  Fecha en formato Y-m-d.
     * @param  array  $ventasData  Arreglo de [total, metodo_pago].
     * @param  array  $retirosData  Arreglo de montos de retiro.
     */
    private function crearDatosDia(string $fecha, array $ventasData = [], array $retirosData = []): void
    {
        $fechaHora = $fecha.' 12:00:00';

        foreach ($ventasData as $ventaInfo) {
            $comanda = Comanda::create([
                'mesa_id' => $this->mesa->id,
                'usuario_id' => $this->cajero->id,
                'canal' => 'mesa',
                'estado' => 'pagada',
                'subtotal' => $ventaInfo[0],
            ]);

            $venta = Venta::forceCreate([
                'comanda_id' => $comanda->id,
                'usuario_id' => $this->cajero->id,
                'total' => $ventaInfo[0],
                'monto_recibido' => $ventaInfo[0],
                'cambio' => 0.00,
                'metodo_pago' => $ventaInfo[1] ?? 'efectivo',
                'created_at' => $fechaHora,
                'updated_at' => $fechaHora,
            ]);
        }

        foreach ($retirosData as $monto) {
            RetiroParcial::forceCreate([
                'usuario_id' => $this->cajero->id,
                'monto' => $monto,
                'motivo' => 'Retiro de prueba',
                'created_at' => $fechaHora,
                'updated_at' => $fechaHora,
            ]);
        }
    }

    /**
     * Valida que un cierre de caja se procesa correctamente con ventas y retiros del día.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_caja_exitoso_con_ventas_y_retiros(): void
    {
        $fecha = '2026-09-21';

        // Ventas del día: $50 efectivo + $20 transferencia = $70 total
        // Retiros: $30
        $this->crearDatosDia($fecha, [
            [50.00, 'efectivo'],
            [20.00, 'transferencia'],
        ], [30.00]);

        // Saldo inicial: $100, Saldo final: $120
        // Venta del día = (30 + 120) − 100 = 50
        // Diferencia = 50 (efectivo) − 50 (venta_del_dia) = 0
        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 120.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Caja cerrada correctamente.',
                'data' => [
                    'fecha' => $fecha,
                    'saldo_inicial' => '100.00',
                    'total_ventas_efectivo' => '50.00',
                    'total_ventas_transferencia' => '20.00',
                    'total_retiros' => '30.00',
                    'saldo_final' => '120.00',
                    'venta_del_dia' => '50.00',
                    'diferencia' => '0.00',
                ],
            ]);

        $this->assertDatabaseHas('cierres_caja', [
            'fecha' => $fecha,
            'venta_del_dia' => 50.00,
            'diferencia' => 0.00,
        ]);
    }

    /**
     * Valida que un cierre sin ventas ni retiros produce totales en cero.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_caja_sin_ventas_ni_retiros(): void
    {
        $fecha = '2026-09-21';

        // Sin ventas ni retiros; saldo inicial = saldo final = 100
        // Venta del día = (0 + 100) − 100 = 0
        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 100.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_ventas_efectivo' => '0.00',
                    'total_ventas_transferencia' => '0.00',
                    'total_retiros' => '0.00',
                    'venta_del_dia' => '0.00',
                    'diferencia' => '0.00',
                ],
            ]);
    }

    /**
     * Valida que no se permite registrar dos cierres para la misma fecha.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_rechaza_fecha_duplicada(): void
    {
        $fecha = '2026-09-21';

        CierreCaja::create([
            'usuario_id' => $this->cajero->id,
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'total_ventas_efectivo' => 0.00,
            'total_ventas_transferencia' => 0.00,
            'total_retiros' => 0.00,
            'saldo_final' => 100.00,
            'venta_del_dia' => 0.00,
            'diferencia' => 0.00,
        ]);

        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 100.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha'])
            ->assertJsonFragment([
                'fecha' => ['Ya existe un cierre registrado para esta fecha.'],
            ]);
    }

    /**
     * Valida que no se permite un cierre para una fecha futura.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_rechaza_fecha_futura(): void
    {
        $fechaFutura = now()->addDays(5)->format('Y-m-d');

        $payload = [
            'fecha' => $fechaFutura,
            'saldo_inicial' => 100.00,
            'saldo_final' => 100.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);
    }

    /**
     * Valida que cuando hay más efectivo del esperado se refleja como diferencia positiva (sobrante).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_calcula_diferencia_sobrante(): void
    {
        $fecha = '2026-09-21';

        // Ventas en efectivo: $50
        $this->crearDatosDia($fecha, [[50.00, 'efectivo']]);

        // Saldo inicial: $100, Saldo final: $160 (tiene $10 de más)
        // Venta del día = (0 + 160) − 100 = 60
        // Diferencia = 50 − 60 = −10 (faltante según fórmula, pero hay sobrante en caja)
        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 160.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'venta_del_dia' => '60.00',
                    'diferencia' => '-10.00',
                ],
            ]);
    }

    /**
     * Valida que cuando falta efectivo se refleja como diferencia positiva (faltante en caja).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_calcula_diferencia_faltante(): void
    {
        $fecha = '2026-09-21';

        // Ventas en efectivo: $50
        $this->crearDatosDia($fecha, [[50.00, 'efectivo']]);

        // Saldo inicial: $100, Saldo final: $140 (faltan $10)
        // Venta del día = (0 + 140) − 100 = 40
        // Diferencia = 50 − 40 = 10 (faltante: vendió $50 pero solo hay $40 extra)
        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 140.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'venta_del_dia' => '40.00',
                    'diferencia' => '10.00',
                ],
            ]);
    }

    /**
     * Valida que el cierre de caja genera un registro de auditoría.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_cierre_registra_auditoria(): void
    {
        $fecha = '2026-09-21';

        $payload = [
            'fecha' => $fecha,
            'saldo_inicial' => 100.00,
            'saldo_final' => 100.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'cierre_caja',
            'entidad_type' => 'App\\Models\\CierreCaja',
        ]);
    }

    /**
     * Valida la consulta de un cierre existente por fecha.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_consultar_cierre_por_fecha(): void
    {
        $fecha = '2026-09-21';

        CierreCaja::create([
            'usuario_id' => $this->cajero->id,
            'fecha' => $fecha,
            'saldo_inicial' => 200.00,
            'total_ventas_efectivo' => 80.00,
            'total_ventas_transferencia' => 30.00,
            'total_retiros' => 50.00,
            'saldo_final' => 230.00,
            'venta_del_dia' => 80.00,
            'diferencia' => 0.00,
        ]);

        $response = $this->getJson('/api/cierres-caja/'.$fecha);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Cierre de caja obtenido.',
                'data' => [
                    'fecha' => $fecha,
                    'saldo_inicial' => '200.00',
                    'total_ventas_efectivo' => '80.00',
                    'total_ventas_transferencia' => '30.00',
                    'total_retiros' => '50.00',
                    'saldo_final' => '230.00',
                    'venta_del_dia' => '80.00',
                    'diferencia' => '0.00',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'fecha',
                    'saldo_inicial',
                    'total_ventas_efectivo',
                    'total_ventas_transferencia',
                    'total_retiros',
                    'saldo_final',
                    'venta_del_dia',
                    'diferencia',
                    'observaciones',
                    'usuario' => ['id', 'name'],
                ],
            ]);
    }

    /**
     * Valida que el historial de cierres devuelve una respuesta paginada.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-22
     *
     * @módulo POS – RF-POS-002 / CC-41
     */
    public function test_historial_cierres_paginado(): void
    {
        $baseData = [
            'usuario_id' => $this->cajero->id,
            'saldo_inicial' => 100.00,
            'total_ventas_efectivo' => 0.00,
            'total_ventas_transferencia' => 0.00,
            'total_retiros' => 0.00,
            'saldo_final' => 100.00,
            'venta_del_dia' => 0.00,
            'diferencia' => 0.00,
        ];

        CierreCaja::create(array_merge($baseData, ['fecha' => '2026-09-19']));
        CierreCaja::create(array_merge($baseData, ['fecha' => '2026-09-20']));
        CierreCaja::create(array_merge($baseData, ['fecha' => '2026-09-21']));

        $response = $this->getJson('/api/cierres-caja?por_pagina=2');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'Historial de cierres obtenido.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'current_page',
                    'data',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('data.per_page', 2)
            ->assertJsonPath('data.total', 3);
    }
}
