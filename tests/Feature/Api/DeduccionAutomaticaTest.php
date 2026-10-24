<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de pruebas integrales para la deducción automatizada de stock por receta estándar.
 * Valida de punta a punta el cumplimiento del criterio de aceptación CC-50 (US-INV-01 / RF-INV-003).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-003 / CC-50
 */
class DeduccionAutomaticaTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected Mesa $mesa;
    protected Platillo $platilloCarne;
    protected Platillo $platilloPollo;
    protected Platillo $platilloRefresco;
    protected Insumo $insumoCarne;
    protected Insumo $insumoArroz;
    protected Insumo $insumoAceite;
    protected Insumo $insumoSal;

    /**
     * Configuración del entorno de prueba con insumos, platillos y recetas vinculadas.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $this->cajero = User::factory()->create();
        $this->cajero->assignRole('cajero');
        Sanctum::actingAs($this->cajero);

        $this->mesa = Mesa::create([
            'numero'    => 5,
            'capacidad' => 4,
            'estado'    => 'ocupada',
        ]);

        // Insumos en bodega
        $this->insumoCarne = Insumo::create([
            'nombre'        => 'Carne de Res Molida',
            'unidad_medida' => 'lb',
            'stock_actual'  => 50.000000,
            'stock_minimo'  => 10.000000,
        ]);

        $this->insumoArroz = Insumo::create([
            'nombre'        => 'Arroz Blanco',
            'unidad_medida' => 'kg',
            'stock_actual'  => 20.000000,
            'stock_minimo'  => 5.000000,
        ]);

        $this->insumoAceite = Insumo::create([
            'nombre'        => 'Aceite Vegetal',
            'unidad_medida' => 'lt',
            'stock_actual'  => 15.000000,
            'stock_minimo'  => 3.000000,
        ]);

        $this->insumoSal = Insumo::create([
            'nombre'        => 'Sal Fina',
            'unidad_medida' => 'kg',
            'stock_actual'  => 2.000000,
            'stock_minimo'  => 0.500000,
        ]);

        // Platillos del menú
        $this->platilloCarne = Platillo::create([
            'nombre'          => 'Hamburguesa Especial',
            'descripcion'     => 'Hamburguesa con carne y guarnición',
            'precio_unitario' => 6.00,
            'disponible'      => true,
        ]);

        $this->platilloPollo = Platillo::create([
            'nombre'          => 'Pollo con Arroz',
            'descripcion'     => 'Plato fuerte con guarnición de arroz',
            'precio_unitario' => 5.00,
            'disponible'      => true,
        ]);

        $this->platilloRefresco = Platillo::create([
            'nombre'          => 'Gaseosa en Lata',
            'descripcion'     => 'Bebida sin receta de insumos de cocina',
            'precio_unitario' => 1.50,
            'disponible'      => true,
        ]);

        // Recetas: Hamburguesa (0.25 lb carne, 20 ml aceite, 0.005 kg sal)
        Receta::create([
            'platillo_id'          => $this->platilloCarne->id,
            'insumo_id'            => $this->insumoCarne->id,
            'cantidad_por_porcion' => 0.250000,
            'unidad_consumo'       => 'lb',
        ]);
        Receta::create([
            'platillo_id'          => $this->platilloCarne->id,
            'insumo_id'            => $this->insumoAceite->id,
            'cantidad_por_porcion' => 20.000000, // 20 ml -> 0.02 lt
            'unidad_consumo'       => 'ml',
        ]);
        Receta::create([
            'platillo_id'          => $this->platilloCarne->id,
            'insumo_id'            => $this->insumoSal->id,
            'cantidad_por_porcion' => 0.005000, // 5 g = 0.005 kg
            'unidad_consumo'       => 'kg',
        ]);

        // Recetas: Pollo con Arroz (200 g arroz -> 0.2 kg, 15 ml aceite -> 0.015 lt)
        Receta::create([
            'platillo_id'          => $this->platilloPollo->id,
            'insumo_id'            => $this->insumoArroz->id,
            'cantidad_por_porcion' => 200.000000, // 200 g
            'unidad_consumo'       => 'g',
        ]);
        Receta::create([
            'platillo_id'          => $this->platilloPollo->id,
            'insumo_id'            => $this->insumoAceite->id,
            'cantidad_por_porcion' => 15.000000, // 15 ml
            'unidad_consumo'       => 'ml',
        ]);
    }

    /**
     * Valida que el cobro de una comanda descuenta automáticamente el inventario según la receta (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_cobro_comanda_descuenta_automaticamente_inventario_por_receta_estandar(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 12.00,
        ]);

        // 2 Hamburguesas vendidas
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 2,
            'precio_unitario' => 6.00,
        ]);

        $payload = [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 20.00,
            'metodo_pago'    => 'efectivo',
        ];

        $response = $this->postJson('/api/cobros', $payload);

        $response->assertStatus(201);

        // Carne: 50 lb - (0.25 * 2) = 49.50 lb
        $this->assertEquals(49.500000, (float) $this->insumoCarne->fresh()->stock_actual);

        // Aceite: 15 lt - (20 ml * 2 = 40 ml = 0.04 lt) = 14.96 lt
        $this->assertEquals(14.960000, (float) $this->insumoAceite->fresh()->stock_actual);

        // Sal: 2 kg - (0.005 * 2 = 0.010 kg) = 1.990 kg
        $this->assertEquals(1.990000, (float) $this->insumoSal->fresh()->stock_actual);
    }

    /**
     * Valida que la deducción aplica la conversión métrica de unidades de consumo a unidades de bodega (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_deduccion_aplica_conversion_de_unidades_de_consumo_a_bodega(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 15.00,
        ]);

        // 3 Porciones de Pollo con Arroz
        // Arroz: 200 g * 3 = 600 g -> En bodega se almacena en kg (600 / 1000 = 0.6 kg)
        // Aceite: 15 ml * 3 = 45 ml -> En bodega se almacena en lt (45 / 1000 = 0.045 lt)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloPollo->id,
            'cantidad'        => 3,
            'precio_unitario' => 5.00,
        ]);

        $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 15.00,
        ])->assertStatus(201);

        // Arroz: 20.00 kg - 0.60 kg = 19.40 kg
        $this->assertEquals(19.400000, (float) $this->insumoArroz->fresh()->stock_actual);

        // Aceite: 15.00 lt - 0.045 lt = 14.955 lt
        $this->assertEquals(14.955000, (float) $this->insumoAceite->fresh()->stock_actual);
    }

    /**
     * Valida que los insumos compartidos entre distintos platillos se consolidan en una sola deducción (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_deduccion_consolida_insumos_compartidos_en_la_misma_comanda(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 17.00,
        ]);

        // 2 Hamburguesas (consume 20 ml * 2 = 40 ml de aceite)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 2,
            'precio_unitario' => 6.00,
        ]);

        // 1 Pollo con Arroz (consume 15 ml * 1 = 15 ml de aceite)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloPollo->id,
            'cantidad'        => 1,
            'precio_unitario' => 5.00,
        ]);

        // Total aceite a descontar: 40 ml + 15 ml = 55 ml = 0.055 lt
        $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 20.00,
        ])->assertStatus(201);

        // 15.000000 - 0.055000 = 14.945000 lt
        $this->assertEquals(14.945000, (float) $this->insumoAceite->fresh()->stock_actual);
    }

    /**
     * Valida que platillos sin receta no interrumpen la deducción de los platillos con receta (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_cobro_de_comanda_con_platillo_sin_receta_no_falla_y_descuenta_los_existentes(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 9.00,
        ]);

        // 1 Hamburguesa (con receta)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 1,
            'precio_unitario' => 6.00,
        ]);

        // 2 Refrescos en lata (sin receta de insumos)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloRefresco->id,
            'cantidad'        => 2,
            'precio_unitario' => 1.50,
        ]);

        $response = $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 10.00,
        ]);

        $response->assertStatus(201);

        // Solo se descuenta la hamburguesa: 0.25 lb carne
        $this->assertEquals(49.750000, (float) $this->insumoCarne->fresh()->stock_actual);
    }

    /**
     * Valida que marcar comanda como pagada vía endpoint PATCH comanda también descuenta inventario (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_marcar_comanda_como_pagada_via_endpoint_comandas_descuenta_inventario(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 10.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloPollo->id,
            'cantidad'        => 2, // 400 g arroz = 0.4 kg
            'precio_unitario' => 5.00,
        ]);

        $response = $this->patchJson("/api/comandas/{$comanda->id}/estado", [
            'estado' => 'pagada',
        ]);

        $response->assertStatus(200);

        // 20 kg - 0.4 kg = 19.6 kg
        $this->assertEquals(19.600000, (float) $this->insumoArroz->fresh()->stock_actual);
    }

    /**
     * Valida que si el cobro es rechazado (monto insuficiente), no se descuenta ningún insumo (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_rechazo_de_cobro_insuficiente_no_descuenta_inventario(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 12.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 2,
            'precio_unitario' => 6.00,
        ]);

        $response = $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 10.00, // Insuficiente para $12.00
        ]);

        $response->assertStatus(422);

        // El stock debe permanecer intacto
        $this->assertEquals(50.000000, (float) $this->insumoCarne->fresh()->stock_actual);
        $this->assertEquals(15.000000, (float) $this->insumoAceite->fresh()->stock_actual);
        $this->assertEquals(2.000000, (float) $this->insumoSal->fresh()->stock_actual);
    }

    /**
     * Valida que una comanda cancelada no descuenta inventario (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_comanda_cancelada_no_descuenta_inventario(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 6.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 1,
            'precio_unitario' => 6.00,
        ]);

        $response = $this->patchJson("/api/comandas/{$comanda->id}/estado", [
            'estado' => 'cancelada',
        ]);

        $response->assertStatus(200);

        // Ningún insumo debe descontarse
        $this->assertEquals(50.000000, (float) $this->insumoCarne->fresh()->stock_actual);
        $this->assertEquals(15.000000, (float) $this->insumoAceite->fresh()->stock_actual);
    }

    /**
     * Valida la idempotencia: intentar cobrar una comanda ya pagada no duplica la deducción (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_no_ocurre_doble_deduccion_al_intentar_recobrar_comanda_pagada(): void
    {
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 6.00,
        ]);

        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 1, // 0.25 lb carne
            'precio_unitario' => 6.00,
        ]);

        // Primer cobro exitoso
        $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 10.00,
        ])->assertStatus(201);

        $this->assertEquals(49.750000, (float) $this->insumoCarne->fresh()->stock_actual);

        // Segundo intento de cobro sobre la misma orden debe ser rechazado
        $responseSegundo = $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 10.00,
        ]);

        $responseSegundo->assertStatus(422);

        // El stock no debe volver a descontarse
        $this->assertEquals(49.750000, (float) $this->insumoCarne->fresh()->stock_actual);
    }

    /**
     * Valida que cuando el stock tras la deducción alcanza o cae bajo el mínimo se detecta quiebre/alerta (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_deduccion_activa_condicion_de_alerta_cuando_stock_alcanza_o_baja_del_minimo(): void
    {
        // Forzamos un stock cercano al mínimo de 10 lb
        $this->insumoCarne->update([
            'stock_actual' => 10.500000,
            'stock_minimo' => 10.000000,
        ]);

        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 18.00,
        ]);

        // 3 hamburguesas = 0.75 lb de carne
        // Stock resultante: 10.50 - 0.75 = 9.75 lb (menor al mínimo de 10.00 lb)
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 3,
            'precio_unitario' => 6.00,
        ]);

        $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 20.00,
        ])->assertStatus(201);

        $insumoActualizado = $this->insumoCarne->fresh();
        $this->assertEquals(9.750000, (float) $insumoActualizado->stock_actual);

        // Se verifica la condición de stock bajo
        $this->assertTrue((float) $insumoActualizado->stock_actual <= (float) $insumoActualizado->stock_minimo);
    }

    /**
     * Valida deducciones fraccionarias de alta precisión con hasta seis decimales (CC-50).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-003 / CC-50
     *
     * @return void
     */
    public function test_deduccion_con_precision_de_seis_decimales(): void
    {
        // Sal tiene stock 2.000000 kg, receta usa 0.005000 kg por hamburguesa
        $comanda = Comanda::create([
            'mesa_id'    => $this->mesa->id,
            'usuario_id' => $this->cajero->id,
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 18.00,
        ]);

        // 7 Hamburguesas = 7 * 0.005000 = 0.035000 kg
        $comanda->detalles()->create([
            'platillo_id'     => $this->platilloCarne->id,
            'cantidad'        => 7,
            'precio_unitario' => 6.00,
        ]);

        $this->postJson('/api/cobros', [
            'comanda_id'     => $comanda->id,
            'monto_recibido' => 20.00,
        ])->assertStatus(201);

        // 2.000000 - 0.035000 = 1.965000 kg
        $this->assertEquals(1.965000, (float) $this->insumoSal->fresh()->stock_actual);
    }
}
