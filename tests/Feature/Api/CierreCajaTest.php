<?php

namespace Tests\Feature\Api;

use App\Models\AperturaCaja;
use App\Models\CierreCaja;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\RetiroParcial;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
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

        foreach (['administrador', 'cajero', 'mesero', 'cocinero', 'contadora'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');
        Sanctum::actingAs($this->cajero);

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
     * @param  string  $hora  Hora de registro en formato H:i:s.
     */
    private function crearDatosDia(
        string $fecha,
        array $ventasData = [],
        array $retirosData = [],
        string $hora = '12:00:00'
    ): void {
        $fechaHora = $fecha.' '.$hora;

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
     * Registra la apertura de caja de una fecha con el saldo inicial indicado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @param  string  $fecha  Fecha en formato Y-m-d.
     * @param  float  $saldoInicial  Saldo inicial contado en la apertura.
     * @return void
     */
    private function abrirCaja(string $fecha, float $saldoInicial = 100.00): void
    {
        AperturaCaja::create([
            'usuario_id'     => $this->cajero->id,
            'fecha'          => $fecha,
            'saldo_inicial'  => $saldoInicial,
            'denominaciones' => [[
                'denominacion' => $saldoInicial,
                'tipo'         => 'billete',
                'cantidad'     => 1,
                'subtotal'     => $saldoInicial,
            ]],
        ]);
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
        $this->abrirCaja($fecha, 100.00);

        // Saldo inicial (apertura): $100, Saldo final: $120
        // Venta del día = (30 + 120) − 100 = 50
        // Diferencia = 50 (venta_del_dia) − 50 (efectivo) = 0
        $payload = [
            'fecha' => $fecha,
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
        $this->abrirCaja($fecha, 100.00);
        $payload = [
            'fecha' => $fecha,
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
        $this->abrirCaja($fecha);

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

        $this->abrirCaja($fecha, 100.00);

        // Esperado en caja: 100 + 50 = 150; contado: 160 (hay $10 de más)
        // Venta del día = (0 + 160) − 100 = 60
        // Diferencia = 60 − 50 = +10 (positivo = sobrante)
        $payload = [
            'fecha' => $fecha,
            'saldo_final' => 160.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'venta_del_dia' => '60.00',
                    'diferencia' => '10.00',
                ],
            ]);
    }

    /**
     * Valida que cuando falta efectivo se refleja como diferencia negativa (faltante).
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

        $this->abrirCaja($fecha, 100.00);

        // Esperado en caja: 100 + 50 = 150; contado: 140 (faltan $10)
        // Venta del día = (0 + 140) − 100 = 40
        // Diferencia = 40 − 50 = −10 (negativo = faltante)
        $payload = [
            'fecha' => $fecha,
            'saldo_final' => 140.00,
        ];

        $response = $this->postJson('/api/cierres-caja', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'venta_del_dia' => '40.00',
                    'diferencia' => '-10.00',
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
        $this->abrirCaja($fecha);

        $payload = [
            'fecha' => $fecha,
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

    /**
     * Valida que sin sesión no se puede cerrar ni consultar la caja.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_requiere_sesion(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 100.00,
        ])->assertStatus(401)->assertJsonPath('status', 'error');

        $this->getJson('/api/cierres-caja')->assertStatus(401);
        $this->getJson('/api/cierres-caja/2026-09-21')->assertStatus(401);

        $this->assertDatabaseCount('cierres_caja', 0);
    }

    /**
     * Roles que según la matriz RBAC no pueden cerrar la caja (CLAUDE.md §9).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return array<string, array{string}>
     */
    public static function rolesSinAccesoACaja(): array
    {
        return [
            'mesero'    => ['mesero'],
            'cocinero'  => ['cocinero'],
            'contadora' => ['contadora'],
        ];
    }

    /**
     * Valida que mesero, cocinero y contadora no pueden cerrar ni consultar cierres.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @param  string  $rol  Rol sin acceso a la caja.
     * @return void
     */
    #[DataProvider('rolesSinAccesoACaja')]
    public function test_rol_sin_acceso_no_puede_cerrar_caja(string $rol): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);
        Sanctum::actingAs($usuario);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 100.00,
        ])->assertStatus(403)->assertJsonPath('status', 'error');

        $this->getJson('/api/cierres-caja')->assertStatus(403);

        $this->assertDatabaseCount('cierres_caja', 0);
    }

    /**
     * Valida que el administrador también puede realizar el cierre diario.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_administrador_puede_cerrar_caja(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        Sanctum::actingAs($admin);
        $this->abrirCaja('2026-09-21');

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 100.00,
        ])->assertStatus(201)
            ->assertJsonPath('data.usuario.id', $admin->id);
    }

    /**
     * Valida que el cierre y su auditoría quedan a nombre del usuario autenticado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_queda_a_nombre_del_usuario_autenticado(): void
    {
        User::factory()->create();
        $this->abrirCaja('2026-09-21');

        $response = $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 100.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.usuario.id', $this->cajero->id)
            ->assertJsonPath('data.usuario.name', $this->cajero->name)
            ->assertJsonMissingPath('data.usuario.roles')
            ->assertJsonMissingPath('data.usuario.permisos');

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->cajero->id,
            'accion'     => 'cierre_caja',
            'entidad_id' => $response->json('data.id'),
        ]);
    }

    /**
     * Valida que el cuadre solo considera ventas y retiros de la fecha cerrada,
     * incluyendo los registrados al primer y último segundo del día.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_solo_considera_movimientos_del_dia(): void
    {
        $this->crearDatosDia('2026-09-20', [[99.00, 'efectivo']], [40.00], '23:59:59');
        $this->crearDatosDia('2026-09-21', [[10.00, 'efectivo']], [5.00], '00:00:00');
        $this->crearDatosDia('2026-09-21', [[15.00, 'efectivo']], [5.00], '23:59:59');
        $this->crearDatosDia('2026-09-22', [[77.00, 'efectivo']], [60.00], '00:00:00');
        $this->abrirCaja('2026-09-21', 100.00);

        // Venta del día = (10 + 115) − 100 = 25; diferencia = 25 − 25 = 0
        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 115.00,
        ])->assertStatus(201)
            ->assertJsonPath('data.total_ventas_efectivo', '25.00')
            ->assertJsonPath('data.total_retiros', '10.00')
            ->assertJsonPath('data.venta_del_dia', '25.00')
            ->assertJsonPath('data.diferencia', '0.00');
    }

    /**
     * Valida que el cierre registra lo vendido por cada método de pago y que solo
     * el efectivo entra al cuadre de caja.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_registra_ventas_por_metodo_de_pago(): void
    {
        $fecha = '2026-09-21';

        $this->crearDatosDia($fecha, [
            [50.00, 'efectivo'],
            [30.00, 'tarjeta'],
            [12.50, 'tarjeta'],
            [20.00, 'transferencia'],
        ]);
        $this->abrirCaja($fecha, 100.00);

        // Solo los $50 en efectivo entran a la gaveta: esperado 150, contado 150
        $this->postJson('/api/cierres-caja', [
            'fecha'       => $fecha,
            'saldo_final' => 150.00,
        ])->assertStatus(201)
            ->assertJsonPath('data.total_ventas_efectivo', '50.00')
            ->assertJsonPath('data.total_ventas_tarjeta', '42.50')
            ->assertJsonPath('data.total_ventas_transferencia', '20.00')
            ->assertJsonPath('data.venta_del_dia', '50.00')
            ->assertJsonPath('data.diferencia', '0.00');

        $this->assertDatabaseHas('cierres_caja', [
            'fecha'                => $fecha,
            'total_ventas_tarjeta' => 42.50,
        ]);
    }

    /**
     * Valida que montos con centavos se suman sin errores de punto flotante.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_suma_centavos_con_precision_de_dos_decimales(): void
    {
        $fecha = '2026-09-21';

        $this->crearDatosDia($fecha, [
            [10.10, 'efectivo'],
            [20.20, 'efectivo'],
            [0.70, 'efectivo'],
        ], [0.10, 0.20]);
        $this->abrirCaja($fecha, 100.00);

        // Venta del día = (0.30 + 130.70) − 100.00 = 31.00
        $this->postJson('/api/cierres-caja', [
            'fecha'         => $fecha,
            'saldo_final'   => 130.70,
        ])->assertStatus(201)
            ->assertJsonPath('data.total_ventas_efectivo', '31.00')
            ->assertJsonPath('data.total_retiros', '0.30')
            ->assertJsonPath('data.venta_del_dia', '31.00')
            ->assertJsonPath('data.diferencia', '0.00');
    }

    /**
     * Valida que el cierre rechaza datos faltantes, negativos o no numéricos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_rechaza_datos_invalidos(): void
    {
        $this->postJson('/api/cierres-caja', [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['fecha', 'saldo_final']);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 'cien',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['saldo_final']);

        $this->postJson('/api/cierres-caja', [
            'fecha'       => '2026-09-21',
            'saldo_final' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['saldo_final']);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-09-21',
            'saldo_final'   => 100.00,
            'observaciones' => str_repeat('a', 501),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['observaciones']);

        $this->assertDatabaseCount('cierres_caja', 0);
    }

    /**
     * Valida que la bitácora de cierre no expone forma de modificarla ni eliminarla.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_bitacora_de_cierre_es_inalterable(): void
    {
        $fecha = '2026-09-21';
        $this->abrirCaja($fecha);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => $fecha,
            'saldo_final'   => 100.00,
        ])->assertStatus(201);

        $cambios = ['saldo_final' => 999.00];

        $this->putJson('/api/cierres-caja/'.$fecha, $cambios)->assertStatus(405);
        $this->patchJson('/api/cierres-caja/'.$fecha, $cambios)->assertStatus(405);
        $this->deleteJson('/api/cierres-caja/'.$fecha)->assertStatus(405);

        $this->assertDatabaseHas('cierres_caja', [
            'fecha'       => $fecha,
            'saldo_final' => 100.00,
        ]);
    }

    /**
     * Valida que consultar una fecha sin cierre responde 404 con el contrato de la API.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_consultar_fecha_sin_cierre_devuelve_404(): void
    {
        $this->getJson('/api/cierres-caja/2026-09-21')
            ->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message', 'data']);
    }

    /**
     * Valida que de noche el cierre usa la fecha de El Salvador y no la de UTC:
     * a las 7:30 p. m. del 19 se puede cerrar el 19 y el 20 sigue siendo fecha futura.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_nocturno_usa_la_fecha_local(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 19:30:00', 'America/El_Salvador'));

        $this->crearDatosDia('2026-10-19', [[12.00, 'efectivo']], [], '19:00:00');
        $this->abrirCaja('2026-10-19', 100.00);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-10-20',
            'saldo_final'   => 112.00,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha']);

        $this->postJson('/api/cierres-caja', [
            'fecha'         => '2026-10-19',
            'saldo_final'   => 112.00,
        ])->assertStatus(201)
            ->assertJsonPath('data.total_ventas_efectivo', '12.00')
            ->assertJsonPath('data.diferencia', '0.00');

        Carbon::setTestNow();
    }

    /**
     * Valida que el saldo inicial del cierre es el de la apertura, aunque se envíe otro.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_toma_el_saldo_inicial_de_la_apertura(): void
    {
        $fecha = '2026-09-21';

        $this->crearDatosDia($fecha, [[20.00, 'efectivo']]);
        $this->abrirCaja($fecha, 80.00);

        // Un saldo inicial enviado desde el cliente se ignora: no puede maquillar el cuadre
        $this->postJson('/api/cierres-caja', [
            'fecha'         => $fecha,
            'saldo_inicial' => 999.00,
            'saldo_final'   => 100.00,
        ])->assertStatus(201)
            ->assertJsonPath('data.saldo_inicial', '80.00')
            ->assertJsonPath('data.venta_del_dia', '20.00')
            ->assertJsonPath('data.diferencia', '0.00');
    }

    /**
     * Valida que no se puede cerrar la caja de un día que no tuvo apertura.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo POS – US-POS-04 / RF-POS-002 (CC-43)
     *
     * @return void
     */
    public function test_cierre_sin_apertura_es_rechazado(): void
    {
        $this->postJson('/api/cierres-caja', [
            'fecha'       => '2026-09-21',
            'saldo_final' => 100.00,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment([
                'fecha' => ['Debes registrar la apertura de caja de esta fecha antes de cerrarla.'],
            ]);

        $this->assertDatabaseCount('cierres_caja', 0);
    }
}
