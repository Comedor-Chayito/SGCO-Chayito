<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\User;
use Database\Seeders\PosSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas del catálogo de mesas para el panel de configuración (US-ADM-03 / CC-107 / CC-112).
 *
 * Cubre:
 * - CRUD completo de mesas con número y capacidad.
 * - Validación de unicidad de número y límites de capacidad.
 * - Alternancia de estado activo / inactivo (toggle).
 * - Eliminación física de mesas sin historial de comandas.
 * - Desactivación lógica automática en lugar de borrado si la mesa posee comandas históricas.
 * - Protección contra retiro o desactivación si la mesa tiene comandas abiertas (pendiente, en_preparacion, lista).
 * - Exclusión de mesas inactivas en el flujo operativo del POS y rechazo al registrar pedidos.
 * - Control de acceso RBAC estricto (403 a roles no autorizados, 401 sin sesión).
 *
 * @autor  Equipo SGCO-Chayito
 *
 * @fecha  2026-11-18
 *
 * @módulo Administración – US-ADM-03 (CC-107 / CC-112)
 */
class MesaTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $socio;

    protected User $cajero;

    protected User $mesero;

    protected User $cocinero;

    protected User $contadora;

    /**
     * Configuración del entorno de prueba con roles y catálogo base.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PosSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->socio = User::where('email', 'socio@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();
        $this->cocinero = User::where('email', 'cocinero@sgco-chayito.local')->first();
        $this->contadora = User::where('email', 'contadora@sgco-chayito.local')->first();

        Sanctum::actingAs($this->admin);
    }

    /**
     * 1. Roles no administrativos reciben 403 Forbidden en todos los endpoints del catálogo de mesas.
     */
    public function test_roles_no_administrativos_reciben_403_en_catalogo_mesas(): void
    {
        $mesa = Mesa::first();
        $rolesNoAutorizados = [$this->mesero, $this->cajero, $this->cocinero, $this->contadora];

        $rutas = [
            ['GET',    '/api/mesas/todas'],
            ['POST',   '/api/mesas'],
            ['PUT',    "/api/mesas/{$mesa->id}"],
            ['PATCH',  "/api/mesas/{$mesa->id}/toggle-activo"],
            ['DELETE', "/api/mesas/{$mesa->id}"],
        ];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $response = $this->json($metodo, $url, ['numero' => 99]);
                $this->assertEquals(403, $response->status(), "Ruta {$metodo} {$url} debió ser 403 para {$usuario->email}");
            }
        }
    }

    /**
     * 2. Peticiones sin autenticación reciben 401 Unauthorized.
     */
    public function test_sin_autenticacion_recibe_401(): void
    {
        $this->app['auth']->forgetGuards();
        $mesa = Mesa::first();

        $this->getJson('/api/mesas/todas')->assertStatus(401);
        $this->postJson('/api/mesas', ['numero' => 99])->assertStatus(401);
        $this->putJson("/api/mesas/{$mesa->id}", ['numero' => 99])->assertStatus(401);
        $this->patchJson("/api/mesas/{$mesa->id}/toggle-activo")->assertStatus(401);
        $this->deleteJson("/api/mesas/{$mesa->id}")->assertStatus(401);
    }

    /**
     * 3. El rol socio tiene acceso completo a gestionar el catálogo de mesas.
     */
    public function test_socio_puede_gestionar_catalogo_de_mesas(): void
    {
        Sanctum::actingAs($this->socio);

        $response = $this->getJson('/api/mesas/todas');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $crear = $this->postJson('/api/mesas', [
            'numero' => 88,
            'capacidad' => 4,
        ]);
        $crear->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.numero', 88);
    }

    /**
     * 4. Listar todas las mesas incluye activas e inactivas ordenadas por número.
     */
    public function test_administrador_lista_todas_las_mesas_incluyendo_inactivas_ordenadas_por_numero(): void
    {
        // Desactivar una mesa para verificar que aparezca en el panel de configuración
        $mesa = Mesa::first();
        $mesa->update(['activa' => false]);

        $response = $this->getJson('/api/mesas/todas');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Catálogo completo de mesas obtenido.');

        $mesas = $response->json('data');
        $this->assertIsArray($mesas);
        $this->assertNotEmpty($mesas);

        // Verificar que la mesa inactiva está presente
        $inactivaEncontrada = collect($mesas)->firstWhere('id', $mesa->id);
        $this->assertNotNull($inactivaEncontrada);
        $this->assertFalse($inactivaEncontrada['activa']);

        // Verificar orden ascendente por número
        $numeros = array_column($mesas, 'numero');
        $numerosOrdenados = $numeros;
        sort($numerosOrdenados);
        $this->assertSame($numerosOrdenados, $numeros);
    }

    /**
     * 5. Registrar una mesa nueva con datos válidos retorna 201 y estado libre/activa.
     */
    public function test_administrador_crea_mesa_correctamente_con_codigo_201(): void
    {
        $response = $this->postJson('/api/mesas', [
            'numero' => 25,
            'capacidad' => 6,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Mesa registrada correctamente.')
            ->assertJsonPath('data.numero', 25)
            ->assertJsonPath('data.capacidad', 6)
            ->assertJsonPath('data.estado', 'libre')
            ->assertJsonPath('data.activa', true);

        $this->assertDatabaseHas('mesas', [
            'numero' => 25,
            'capacidad' => 6,
            'estado' => 'libre',
            'activa' => 1,
        ]);
    }

    /**
     * 6. Validaciones al crear mesa: número obligatorio, positivo y capacidad válida.
     */
    public function test_crear_mesa_valida_campos_requeridos_y_tipos(): void
    {
        // Sin número
        $this->postJson('/api/mesas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);

        // Número 0 o negativo
        $this->postJson('/api/mesas', ['numero' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);

        $this->postJson('/api/mesas', ['numero' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);

        // Capacidad no entera o negativa o mayor a 255
        $this->postJson('/api/mesas', ['numero' => 30, 'capacidad' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capacidad']);

        $this->postJson('/api/mesas', ['numero' => 30, 'capacidad' => 300])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capacidad']);
    }

    /**
     * 7. No se permite crear una mesa con número duplicado.
     */
    public function test_crear_mesa_rechaza_numero_duplicado(): void
    {
        $mesaExistente = Mesa::first();

        $response = $this->postJson('/api/mesas', [
            'numero' => $mesaExistente->numero,
            'capacidad' => 4,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    /**
     * 8. Actualizar mesa con número y capacidad válidos.
     */
    public function test_administrador_actualiza_numero_y_capacidad_de_mesa(): void
    {
        $mesa = Mesa::create([
            'numero' => 77,
            'capacidad' => 2,
            'estado' => 'libre',
            'activa' => true,
        ]);

        $response = $this->putJson("/api/mesas/{$mesa->id}", [
            'numero' => 78,
            'capacidad' => 8,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Mesa actualizada correctamente.')
            ->assertJsonPath('data.numero', 78)
            ->assertJsonPath('data.capacidad', 8);

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'numero' => 78,
            'capacidad' => 8,
        ]);
    }

    /**
     * 9. Actualizar mesa permite conservar su propio número sin error de unicidad.
     */
    public function test_actualizar_mesa_permite_conservar_el_mismo_numero(): void
    {
        $mesa = Mesa::create([
            'numero' => 65,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        $response = $this->putJson("/api/mesas/{$mesa->id}", [
            'numero' => 65,
            'capacidad' => 6,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.capacidad', 6);
    }

    /**
     * 10. Actualizar mesa rechaza números duplicados pertenecientes a otra mesa.
     */
    public function test_actualizar_mesa_rechaza_numero_duplicado_de_otra_mesa(): void
    {
        $mesa1 = Mesa::create(['numero' => 51, 'capacidad' => 2, 'estado' => 'libre', 'activa' => true]);
        $mesa2 = Mesa::create(['numero' => 52, 'capacidad' => 4, 'estado' => 'libre', 'activa' => true]);

        $response = $this->putJson("/api/mesas/{$mesa2->id}", [
            'numero' => 51,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    /**
     * 11. Eliminar una mesa sin historial de comandas la borra físicamente.
     */
    public function test_elimina_fisicamente_mesa_sin_comandas(): void
    {
        $mesa = Mesa::create([
            'numero' => 90,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        $response = $this->deleteJson("/api/mesas/{$mesa->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Mesa eliminada correctamente.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
    }

    /**
     * 12. Eliminar una mesa con comandas históricas cerradas la desactiva automáticamente en lugar de borrarla.
     */
    public function test_eliminar_mesa_con_comandas_historicas_la_desactiva_automaticamente(): void
    {
        $mesa = Mesa::create([
            'numero' => 91,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        // Comanda histórica ya pagada
        Comanda::create([
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'canal' => 'mesa',
            'estado' => 'pagada',
            'subtotal' => 10.00,
        ]);

        $response = $this->deleteJson("/api/mesas/{$mesa->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'La mesa tiene comandas registradas; se desactivó en lugar de eliminarse.')
            ->assertJsonPath('data.id', $mesa->id)
            ->assertJsonPath('data.activa', false);

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'activa' => 0,
        ]);
    }

    /**
     * 13. Eliminar una mesa con comandas abiertas (pendiente, en_preparacion, lista) es rechazado con 422.
     */
    public function test_eliminar_mesa_con_comandas_abiertas_es_rechazado_con_422(): void
    {
        $mesa = Mesa::create([
            'numero' => 92,
            'capacidad' => 4,
            'estado' => 'ocupada',
            'activa' => true,
        ]);

        Comanda::create([
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'canal' => 'mesa',
            'estado' => 'pendiente',
            'subtotal' => 12.50,
        ]);

        $response = $this->deleteJson("/api/mesas/{$mesa->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mesa']);

        $this->assertDatabaseHas('mesas', [
            'id' => $mesa->id,
            'activa' => 1,
        ]);
    }

    /**
     * 14. Alternar estado activo / inactivo de una mesa (toggle).
     */
    public function test_administrador_desactiva_y_reactiva_mesa(): void
    {
        $mesa = Mesa::create([
            'numero' => 93,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => true,
        ]);

        // 1. Desactivar
        $respDesactivar = $this->patchJson("/api/mesas/{$mesa->id}/toggle-activo");
        $respDesactivar->assertStatus(200)
            ->assertJsonPath('message', 'Mesa desactivada correctamente.')
            ->assertJsonPath('data.activa', false);

        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'activa' => 0]);

        // 2. Reactivar
        $respReactivar = $this->patchJson("/api/mesas/{$mesa->id}/toggle-activo");
        $respReactivar->assertStatus(200)
            ->assertJsonPath('message', 'Mesa reactivada correctamente.')
            ->assertJsonPath('data.activa', true);

        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'activa' => 1]);
    }

    /**
     * 15. Desactivar una mesa que tiene comandas abiertas se rechaza con 422.
     */
    public function test_desactivar_mesa_con_comandas_abiertas_es_rechazado_con_422(): void
    {
        $mesa = Mesa::create([
            'numero' => 94,
            'capacidad' => 4,
            'estado' => 'ocupada',
            'activa' => true,
        ]);

        Comanda::create([
            'mesa_id' => $mesa->id,
            'usuario_id' => $this->mesero->id,
            'canal' => 'mesa',
            'estado' => 'en_preparacion',
            'subtotal' => 8.00,
        ]);

        $response = $this->patchJson("/api/mesas/{$mesa->id}/toggle-activo");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mesa']);
    }

    /**
     * 16. Una mesa desactivada se excluye del listado operativo de mesas del POS (GET /api/mesas).
     */
    public function test_mesa_inactiva_se_excluye_de_lista_activa_de_pos(): void
    {
        $mesa = Mesa::create([
            'numero' => 95,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => false,
        ]);

        Sanctum::actingAs($this->mesero);
        $response = $this->getJson('/api/mesas');

        $response->assertStatus(200);
        $idsMesas = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($mesa->id, $idsMesas);
    }

    /**
     * 17. Intentar registrar una comanda en una mesa inactiva es rechazado por el POS.
     */
    public function test_mesa_inactiva_no_permite_registrar_comandas_en_pos(): void
    {
        $mesa = Mesa::create([
            'numero' => 96,
            'capacidad' => 4,
            'estado' => 'libre',
            'activa' => false,
        ]);

        $platillo = Platillo::where('disponible', true)->first();

        Sanctum::actingAs($this->mesero);
        $response = $this->postJson('/api/comandas', [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [
                [
                    'platillo_id' => $platillo->id,
                    'cantidad' => 1,
                    'precio_esperado' => (float) $platillo->precio_unitario,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mesa_id']);
    }

    /**
     * 18. Contrato estándar de API en respuestas del catálogo de mesas { status, message, data }.
     */
    public function test_contrato_api_en_respuestas_de_catalogo_mesas(): void
    {
        $response = $this->getJson('/api/mesas/todas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'numero',
                        'capacidad',
                        'estado',
                        'activa',
                    ],
                ],
            ]);
    }
}
