<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del flujo de estados de comanda en cocina:
 * pendiente → en_preparacion → lista → pagada (US-POS-02 / CC-28).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-12
 * @módulo POS – US-POS-02 / RF-POS-001
 */
class EstadoComandaTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;

    /**
     * Configuración inicial antes de cada prueba.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create(['id' => 1]);
    }

    /**
     * Crea una comanda para llevar con el estado indicado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  string $estado Estado inicial de la comanda.
     * @return Comanda
     */
    private function crearComanda(string $estado): Comanda
    {
        return Comanda::create([
            'usuario_id' => $this->usuario->id,
            'canal'      => 'para_llevar',
            'estado'     => $estado,
            'subtotal'   => 5.00,
        ]);
    }

    /**
     * Valida que cocina puede marcar una comanda como lista y que el estado persiste en BD.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    public function test_cocina_puede_marcar_comanda_como_lista(): void
    {
        $comanda = $this->crearComanda('en_preparacion');

        $response = $this->patchJson("/api/comandas/{$comanda->id}/estado", ['estado' => 'lista']);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.estado', 'lista');

        $this->assertDatabaseHas('comandas', ['id' => $comanda->id, 'estado' => 'lista']);
    }

    /**
     * Valida que la cola de cocina separa activas (pendiente, en_preparacion)
     * de completadas (lista, pagada), de modo que una lista no vuelve a la cola.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    public function test_cola_cocina_muestra_listas_y_pagadas_como_completadas(): void
    {
        $pendiente = $this->crearComanda('pendiente');
        $enPreparacion = $this->crearComanda('en_preparacion');
        $lista = $this->crearComanda('lista');
        $pagada = $this->crearComanda('pagada');
        $this->crearComanda('cancelada');

        $response = $this->getJson('/api/comandas/cocina');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.activas')
            ->assertJsonCount(2, 'data.completadas');

        $idsActivas = collect($response->json('data.activas'))->pluck('id')->all();
        $idsCompletadas = collect($response->json('data.completadas'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pendiente->id, $enPreparacion->id], $idsActivas);
        $this->assertEqualsCanonicalizing([$lista->id, $pagada->id], $idsCompletadas);
    }

    /**
     * Valida que caja puede consultar solo las comandas listas para cobro.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-03 / RF-POS-002
     *
     * @return void
     */
    public function test_caja_lista_solo_comandas_listas(): void
    {
        $lista = $this->crearComanda('lista');
        $this->crearComanda('en_preparacion');
        $this->crearComanda('pagada');

        $response = $this->getJson('/api/comandas?estado=lista');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lista->id);
    }

    /**
     * Valida que una comanda ya pagada no puede regresar a la cola de cocina.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-12
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @return void
     */
    public function test_comanda_pagada_no_puede_reactivarse(): void
    {
        $comanda = $this->crearComanda('pagada');

        $response = $this->patchJson("/api/comandas/{$comanda->id}/estado", ['estado' => 'pendiente']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        $this->assertDatabaseHas('comandas', ['id' => $comanda->id, 'estado' => 'pagada']);
    }
}
