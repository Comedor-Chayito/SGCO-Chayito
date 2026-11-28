<?php

namespace Tests\Feature\Api;

use App\Models\CierreCaja;
use App\Models\Comanda;
use App\Models\Platillo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\AbreCajaDelDia;
use Tests\TestCase;

/**
 * Pruebas de la validación del estado de la caja: no se cobra ni se retira sin apertura
 * o con la caja cerrada, no se toman comandas tras el cierre, "pagada" solo se alcanza
 * cobrando y no se cierra con comandas sin cobrar.
 */
class EstadoCajaTest extends TestCase
{
    use AbreCajaDelDia;
    use RefreshDatabase;

    private User $cajero;

    private Platillo $platillo;

    /**
     * Prepara un cajero autenticado y un platillo disponible.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cajero', 'mesero'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');
        Sanctum::actingAs($this->cajero);

        $this->platillo = Platillo::create([
            'nombre' => 'Pupusa revuelta',
            'descripcion' => 'Platillo de prueba',
            'precio_unitario' => 1.00,
            'disponible' => true,
        ]);
    }

    /**
     * Crea una comanda para llevar con un platillo en el estado indicado.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-001
     *
     * @param  string  $estado  Estado inicial de la comanda.
     * @return Comanda Comanda creada.
     */
    private function crearComanda(string $estado = 'lista'): Comanda
    {
        return Comanda::create([
            'usuario_id' => $this->cajero->id,
            'canal' => 'para_llevar',
            'estado' => $estado,
            'subtotal' => 1.00,
        ]);
    }

    /**
     * Registra el cierre de caja de hoy directamente en la base.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return void
     */
    private function cerrarCajaDeHoy(): void
    {
        CierreCaja::create([
            'usuario_id' => $this->cajero->id,
            'fecha' => today()->toDateString(),
            'saldo_inicial' => 0,
            'total_ventas_efectivo' => 0,
            'total_ventas_tarjeta' => 0,
            'total_ventas_transferencia' => 0,
            'total_retiros' => 0,
            'saldo_final' => 0,
            'venta_del_dia' => 0,
            'diferencia' => 0,
        ]);
    }

    /**
     * Valida que no se puede cobrar si la caja de hoy no se ha abierto.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_rechaza_cobro_sin_apertura_de_caja(): void
    {
        $comanda = $this->crearComanda();

        $this->postJson('/api/cobros', ['comanda_id' => $comanda->id, 'monto_recibido' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caja']);

        $this->assertSame('lista', $comanda->fresh()->estado);
        $this->assertDatabaseCount('ventas', 0);
    }

    /**
     * Valida que no se puede cobrar después del cierre de caja del día.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_rechaza_cobro_con_caja_cerrada(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $this->cerrarCajaDeHoy();
        $comanda = $this->crearComanda();

        $this->postJson('/api/cobros', ['comanda_id' => $comanda->id, 'monto_recibido' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caja']);

        $this->assertDatabaseCount('ventas', 0);
    }

    /**
     * Valida que con la caja abierta el cobro se registra normalmente.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @return void
     */
    public function test_permite_cobro_con_caja_abierta(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $comanda = $this->crearComanda();

        $this->postJson('/api/cobros', ['comanda_id' => $comanda->id, 'monto_recibido' => 5])
            ->assertStatus(201);

        $this->assertSame('pagada', $comanda->fresh()->estado);
    }

    /**
     * Valida que no se registran retiros si la caja de hoy no se ha abierto.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – US-POS-03 / CC-37
     *
     * @return void
     */
    public function test_rechaza_retiro_sin_apertura_de_caja(): void
    {
        $this->postJson('/api/retiros', ['monto' => 10, 'motivo' => 'Depósito a caja fuerte'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caja']);

        $this->assertDatabaseCount('retiros_parciales', 0);
    }

    /**
     * Valida que se pueden tomar comandas antes de abrir la caja.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-001 / US-POS-04
     *
     * @return void
     */
    public function test_permite_registrar_comanda_sin_apertura(): void
    {
        $this->postJson('/api/comandas', [
            'canal' => 'para_llevar',
            'items' => [['platillo_id' => $this->platillo->id, 'cantidad' => 1]],
        ])->assertStatus(201);
    }

    /**
     * Valida que no se toman comandas después del cierre de caja del día.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-001 / US-POS-04
     *
     * @return void
     */
    public function test_rechaza_registrar_comanda_con_caja_cerrada(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $this->cerrarCajaDeHoy();

        $this->postJson('/api/comandas', [
            'canal' => 'para_llevar',
            'items' => [['platillo_id' => $this->platillo->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['caja']);

        $this->assertDatabaseCount('comandas', 0);
    }

    /**
     * Valida que el PATCH de estado no acepta "pagada": solo el cobro la registra.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / US-POS-02
     *
     * @return void
     */
    public function test_rechaza_marcar_pagada_por_endpoint_de_estado(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $comanda = $this->crearComanda();

        $this->patchJson("/api/comandas/{$comanda->id}/estado", ['estado' => 'pagada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $this->assertSame('lista', $comanda->fresh()->estado);
        $this->assertDatabaseCount('ventas', 0);
    }

    /**
     * Valida que no se cierra la caja con comandas sin cobrar y que el mensaje las enumera.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return void
     */
    public function test_rechaza_cierre_con_comandas_sin_cobrar(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $pendiente = $this->crearComanda('pendiente');
        $lista = $this->crearComanda('lista');

        $respuesta = $this->postJson('/api/cierres-caja', [
            'fecha' => today()->toDateString(),
            'saldo_final' => 0,
        ]);

        $respuesta->assertStatus(422)->assertJsonValidationErrors(['caja']);
        $this->assertStringContainsString("#{$pendiente->id}", $respuesta->json('errors.caja.0'));
        $this->assertStringContainsString("#{$lista->id}", $respuesta->json('errors.caja.0'));
        $this->assertDatabaseCount('cierres_caja', 0);
    }

    /**
     * Valida que una comanda de un día anterior sin cobrar también bloquea el cierre.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return void
     */
    public function test_rechaza_cierre_con_comanda_abierta_de_dia_anterior(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $antigua = $this->crearComanda('pendiente');
        $antigua->forceFill(['created_at' => now()->subDay()])->save();

        $this->postJson('/api/cierres-caja', [
            'fecha' => today()->toDateString(),
            'saldo_final' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['caja']);
    }

    /**
     * Valida que el cierre procede cuando todas las comandas están cobradas o canceladas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-002 / CC-41
     *
     * @return void
     */
    public function test_permite_cierre_con_comandas_cobradas_o_canceladas(): void
    {
        $this->abrirCajaDelDia($this->cajero);
        $this->crearComanda('pagada');
        $this->crearComanda('cancelada');

        $this->postJson('/api/cierres-caja', [
            'fecha' => today()->toDateString(),
            'saldo_final' => 0,
        ])->assertStatus(201);
    }
}
