<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de envío e impresión de comandas (US-POS-02 / RF-POS-001, Sec. 3.1.2 / CC-30).
 * Valida la generación de tickets para impresoras térmicas de 58 mm y 80 mm, comandos ESC/POS
 * y la recepción automática y ordenada de pedidos en la cola de cocina.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo POS – US-POS-02 / RF-POS-001 (CC-30)
 */
class ImpresionComandaTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;
    protected Mesa $mesa;
    protected Platillo $platillo1;
    protected Platillo $platillo2;
    protected Comanda $comanda;

    /**
     * Configuración del entorno de prueba con comanda de muestra.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = User::factory()->create([
            'name'  => 'Mesero Roberto',
            'email' => 'roberto.mesero@sgco-chayito.local',
        ]);

        $this->mesa = Mesa::create([
            'numero'    => 4,
            'capacidad' => 4,
            'estado'    => 'ocupada',
        ]);

        $this->platillo1 = Platillo::create([
            'nombre'          => 'Puyaso con Guarnición',
            'descripcion'     => 'Corte fino de res al término',
            'precio_unitario' => 6.50,
            'disponible'      => true,
        ]);

        $this->platillo2 = Platillo::create([
            'nombre'          => 'Sopa de Gallina India',
            'descripcion'     => 'Tradicional con verduras',
            'precio_unitario' => 4.00,
            'disponible'      => true,
        ]);

        $this->comanda = Comanda::create([
            'usuario_id'    => $this->usuario->id,
            'mesa_id'       => $this->mesa->id,
            'mesa'          => 'Mesa 4',
            'canal'         => 'mesa',
            'estado'        => 'pendiente',
            'observaciones' => 'Cliente es alérgico a la cebolla cruda',
            'subtotal'      => 17.00,
            'total'         => 17.00,
        ]);

        ComandaDetalle::create([
            'comanda_id'      => $this->comanda->id,
            'platillo_id'     => $this->platillo1->id,
            'cantidad'        => 2,
            'precio_unitario' => 6.50,
            'subtotal'        => 13.00,
            'observaciones'   => 'Término medio, sin sal añadida',
        ]);

        ComandaDetalle::create([
            'comanda_id'      => $this->comanda->id,
            'platillo_id'     => $this->platillo2->id,
            'cantidad'        => 1,
            'precio_unitario' => 4.00,
            'subtotal'        => 4.00,
            'observaciones'   => null,
        ]);
    }

    /**
     * Verifica que el endpoint de impresión genere un ticket para 58 mm con ancho máximo de 32 caracteres.
     *
     * @return void
     */
    public function test_impresion_genera_ticket_58mm_con_ancho_correcto(): void
    {
        $response = $this->getJson("/api/comandas/{$this->comanda->id}/impresion?ancho=58");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Ticket de cocina generado correctamente.',
                'data'    => [
                    'comanda_id' => $this->comanda->id,
                    'ancho_mm'   => 58,
                    'columnas'   => 32,
                ],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'comanda_id',
                    'ancho_mm',
                    'columnas',
                    'lineas',
                    'texto_plano',
                    'escpos_base64',
                ],
            ]);

        $lineas = $response->json('data.lineas');
        $this->assertNotEmpty($lineas);

        foreach ($lineas as $linea) {
            $this->assertLessThanOrEqual(
                32,
                mb_strlen($linea, 'UTF-8'),
                "La línea supera el límite de 32 caracteres para 58 mm: [{$linea}]"
            );
        }
    }

    /**
     * Verifica que el endpoint de impresión genere un ticket para 80 mm con ancho de 48 caracteres.
     *
     * @return void
     */
    public function test_impresion_genera_ticket_80mm_con_ancho_correcto(): void
    {
        $response = $this->getJson("/api/comandas/{$this->comanda->id}/impresion?ancho=80");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'data'    => [
                    'comanda_id' => $this->comanda->id,
                    'ancho_mm'   => 80,
                    'columnas'   => 48,
                ],
            ]);

        $lineas = $response->json('data.lineas');
        $this->assertNotEmpty($lineas);

        foreach ($lineas as $linea) {
            $this->assertLessThanOrEqual(
                48,
                mb_strlen($linea, 'UTF-8'),
                "La línea supera el límite de 48 caracteres para 80 mm: [{$linea}]"
            );
        }
    }

    /**
     * Verifica que el ticket contenga los datos clave de la orden, platillos, mesa y mesero.
     *
     * @return void
     */
    public function test_ticket_incluye_detalles_platillos_cantidades_y_metadatos(): void
    {
        $response = $this->getJson("/api/comandas/{$this->comanda->id}/impresion?ancho=58");

        $texto = $response->json('data.texto_plano');

        $this->assertStringContainsString('COMEDOR CHAYITO', $texto);
        $this->assertStringContainsString("ORDEN: #{$this->comanda->id}", $texto);
        $this->assertStringContainsString('MESA: 4', $texto);
        $this->assertStringContainsString('Mesero Roberto', $texto);
        $this->assertStringContainsString('[2x] Puyaso con Guarnición', $texto);
        $this->assertStringContainsString('[1x] Sopa de Gallina India', $texto);
        $this->assertStringContainsString('Término medio', $texto);
        $this->assertStringContainsString('sin sal', $texto);
        $this->assertStringContainsString('añadida', $texto);
        $this->assertStringContainsString('alérgico', $texto);
        $this->assertStringContainsString('cebolla cruda', $texto);
    }

    /**
     * Verifica que el servicio genere comandos binarios ESC/POS válidos codificados en Base64.
     *
     * @return void
     */
    public function test_ticket_genera_comandos_escpos_en_base64_validos(): void
    {
        $response = $this->getJson("/api/comandas/{$this->comanda->id}/impresion");

        $base64 = $response->json('data.escpos_base64');
        $this->assertNotEmpty($base64);

        $binario = base64_decode($base64);
        $this->assertNotFalse($binario);

        // ESC @: Inicializar impresora (\x1B\x40)
        $this->assertStringStartsWith("\x1B\x40", $binario);

        // GS V 0: Corte de papel (\x1D\x56\x00)
        $this->assertStringEndsWith("\x1D\x56\x00", $binario);
    }

    /**
     * Verifica que una comanda inexistente devuelva 404.
     *
     * @return void
     */
    public function test_impresion_devuelve_404_si_comanda_no_existe(): void
    {
        $response = $this->getJson('/api/comandas/999999/impresion');

        $response->assertStatus(404);
    }

    /**
     * Verifica que una comanda recién registrada aparezca automáticamente en la cola de cocina en orden FIFO.
     *
     * @return void
     */
    public function test_nueva_comanda_aparece_automaticamente_en_cola_de_cocina(): void
    {
        $response = $this->getJson('/api/comandas/cocina');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Cola de cocina obtenida.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'activas',
                    'completadas',
                ],
            ]);

        $activas = $response->json('data.activas');
        $ids = array_column($activas, 'id');

        $this->assertContains($this->comanda->id, $ids);
    }

    /**
     * Verifica que la cola de cocina ordene las comandas según riguroso orden de llegada (FIFO).
     *
     * @return void
     */
    public function test_cola_de_cocina_mantiene_orden_fifo_por_fecha_de_creacion(): void
    {
        // Crear comanda posterior
        $comandaPosterior = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'mesa'       => 'Mesa 1',
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 5.00,
            'total'      => 5.00,
            'created_at' => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/comandas/cocina');
        $activas = $response->json('data.activas');

        $posAnterior = null;
        $posPosterior = null;

        foreach ($activas as $index => $item) {
            if ($item['id'] === $this->comanda->id) {
                $posAnterior = $index;
            }
            if ($item['id'] === $comandaPosterior->id) {
                $posPosterior = $index;
            }
        }

        $this->assertNotNull($posAnterior);
        $this->assertNotNull($posPosterior);
        $this->assertLessThan(
            $posPosterior,
            $posAnterior,
            'La comanda creada primero debe aparecer antes en la cola (FIFO)'
        );
    }

    /**
     * Verifica que la cola de cocina separe correctamente las comandas activas de las completadas o despachadas.
     *
     * @return void
     */
    public function test_cola_de_cocina_separa_comandas_completadas_y_activas(): void
    {
        // Comanda completada (despachada)
        $comandaPagada = Comanda::create([
            'usuario_id' => $this->usuario->id,
            'mesa'       => 'Mesa 2',
            'canal'      => 'mesa',
            'estado'     => 'pagada',
            'subtotal'   => 8.00,
            'total'      => 8.00,
        ]);

        $response = $this->getJson('/api/comandas/cocina');

        $idsActivas = array_column($response->json('data.activas'), 'id');
        $idsCompletadas = array_column($response->json('data.completadas'), 'id');

        $this->assertContains($this->comanda->id, $idsActivas);
        $this->assertNotContains($comandaPagada->id, $idsActivas);
        $this->assertContains($comandaPagada->id, $idsCompletadas);
    }
}
