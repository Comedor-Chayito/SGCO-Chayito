<?php

namespace Tests\Feature\Api;

use App\Models\Egreso;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas del catálogo de subcategorías de egreso para el panel de configuración (US-ADM-03 / CC-108 / CC-112).
 *
 * Cubre:
 * - Creación de subcategorías con validación de nombre único y límite de 80 caracteres.
 * - Renombrado de subcategorías preservando la vinculación histórica con los egresos registrados.
 * - Desactivación y reactivación lógica (toggle) sin borrado físico.
 * - Exclusión de subcategorías inactivas en el formulario operativo de carga de facturas de egresos.
 * - Rechazo al intentar registrar un egreso asociándolo a una subcategoría inactiva.
 * - Control de acceso RBAC estricto: exclusivo para Administrador y Socio (403 para no autorizados, 401 sin sesión).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-18
 * @módulo Administración – US-ADM-03 (CC-108 / CC-112)
 */
class SubcategoriaEgresoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $socio;
    protected User $cajero;
    protected User $mesero;
    protected User $cocinero;
    protected User $contadora;

    /**
     * Configuración del entorno de prueba con roles y subcategorías base.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubcategoriaEgresoSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->socio = User::where('email', 'socio@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();
        $this->cocinero = User::where('email', 'cocinero@sgco-chayito.local')->first();
        $this->contadora = User::where('email', 'contadora@sgco-chayito.local')->first();

        Sanctum::actingAs($this->admin);
    }

    /**
     * 1. Roles no administrativos reciben 403 Forbidden en los endpoints de configuración del catálogo.
     */
    public function test_roles_no_administrativos_reciben_403_en_catalogo_subcategorias_configuracion(): void
    {
        $subcategoria = SubcategoriaEgreso::first();
        $rolesNoAutorizados = [$this->mesero, $this->cajero, $this->cocinero, $this->contadora];

        $rutas = [
            ['GET',   '/api/subcategorias-egreso/todas'],
            ['POST',  '/api/subcategorias-egreso'],
            ['PUT',   "/api/subcategorias-egreso/{$subcategoria->id}"],
            ['PATCH', "/api/subcategorias-egreso/{$subcategoria->id}/toggle-activo"],
        ];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $response = $this->json($metodo, $url, ['nombre' => 'Subcategoría Prueba']);
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
        $subcategoria = SubcategoriaEgreso::first();

        $this->getJson('/api/subcategorias-egreso/todas')->assertStatus(401);
        $this->postJson('/api/subcategorias-egreso', ['nombre' => 'X'])->assertStatus(401);
        $this->putJson("/api/subcategorias-egreso/{$subcategoria->id}", ['nombre' => 'Y'])->assertStatus(401);
        $this->patchJson("/api/subcategorias-egreso/{$subcategoria->id}/toggle-activo")->assertStatus(401);
    }

    /**
     * 3. El rol socio tiene acceso completo a gestionar el catálogo de subcategorías.
     */
    public function test_socio_puede_gestionar_catalogo_de_subcategorias(): void
    {
        Sanctum::actingAs($this->socio);

        $response = $this->getJson('/api/subcategorias-egreso/todas');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $crear = $this->postJson('/api/subcategorias-egreso', [
            'nombre' => 'Honorarios Legales',
        ]);
        $crear->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.nombre', 'Honorarios Legales');
    }

    /**
     * 4. Listar todas las subcategorías incluye activas e inactivas ordenadas por nombre.
     */
    public function test_administrador_lista_todas_las_subcategorias_incluyendo_inactivas_con_su_estado(): void
    {
        $subcategoria = SubcategoriaEgreso::first();
        $subcategoria->update(['activo' => false]);

        $response = $this->getJson('/api/subcategorias-egreso/todas');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Catálogo completo de subcategorías de egreso obtenido.');

        $listado = $response->json('data');
        $this->assertIsArray($listado);
        $this->assertNotEmpty($listado);

        // La inactiva debe estar presente
        $encontrada = collect($listado)->firstWhere('id', $subcategoria->id);
        $this->assertNotNull($encontrada);
        $this->assertFalse($encontrada['activo']);

        // Comprobar orden alfabético por nombre
        $nombres = array_column($listado, 'nombre');
        $nombresEsperados = SubcategoriaEgreso::orderBy('nombre')->pluck('nombre')->all();
        $this->assertSame($nombresEsperados, $nombres);
    }

    /**
     * 5. Registrar una subcategoría nueva retorna 201, trimea espacios y queda activa por defecto.
     */
    public function test_administrador_crea_subcategoria_correctamente_con_codigo_201_y_activa_por_defecto(): void
    {
        $response = $this->postJson('/api/subcategorias-egreso', [
            'nombre' => '  Mantenimiento de Aires Acondicionados  ',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Subcategoría de egreso creada correctamente.')
            ->assertJsonPath('data.nombre', 'Mantenimiento de Aires Acondicionados')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('subcategorias_egreso', [
            'nombre' => 'Mantenimiento de Aires Acondicionados',
            'activo' => 1,
        ]);
    }

    /**
     * 6. Validaciones al crear subcategoría: nombre obligatorio, texto y máximo 80 caracteres.
     */
    public function test_crear_subcategoria_valida_nombre_obligatorio_string_y_longitud_maxima(): void
    {
        // Sin nombre
        $this->postJson('/api/subcategorias-egreso', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Nombre vacío
        $this->postJson('/api/subcategorias-egreso', ['nombre' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Nombre superior a 80 caracteres
        $this->postJson('/api/subcategorias-egreso', ['nombre' => str_repeat('A', 81)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * 7. No se permite crear una subcategoría con nombre duplicado.
     */
    public function test_crear_subcategoria_rechaza_nombre_duplicado(): void
    {
        $existente = SubcategoriaEgreso::first();

        $response = $this->postJson('/api/subcategorias-egreso', [
            'nombre' => $existente->nombre,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * 8. Actualizar / renombrar subcategoría modifica el nombre y preserva el identificador.
     */
    public function test_administrador_renombra_subcategoria_correctamente(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Suministros Varios',
            'activo' => true,
        ]);

        $response = $this->putJson("/api/subcategorias-egreso/{$subcategoria->id}", [
            'nombre' => 'Suministros de Oficina y Papelería',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Subcategoría de egreso renombrada correctamente.')
            ->assertJsonPath('data.id', $subcategoria->id)
            ->assertJsonPath('data.nombre', 'Suministros de Oficina y Papelería');

        $this->assertDatabaseHas('subcategorias_egreso', [
            'id'     => $subcategoria->id,
            'nombre' => 'Suministros de Oficina y Papelería',
        ]);
    }

    /**
     * 9. Renombrar subcategoría permite conservar su propio nombre sin error de unicidad.
     */
    public function test_renombrar_subcategoria_permite_conservar_su_mismo_nombre(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Servicios Sanitarios',
            'activo' => true,
        ]);

        $response = $this->putJson("/api/subcategorias-egreso/{$subcategoria->id}", [
            'nombre' => 'Servicios Sanitarios',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Servicios Sanitarios');
    }

    /**
     * 10. Renombrar subcategoría rechaza nombres duplicados pertenecientes a otra subcategoría.
     */
    public function test_renombrar_subcategoria_rechaza_nombre_duplicado_de_otra(): void
    {
        $sub1 = SubcategoriaEgreso::create(['nombre' => 'Gastos Bancarios', 'activo' => true]);
        $sub2 = SubcategoriaEgreso::create(['nombre' => 'Gastos Financieros', 'activo' => true]);

        $response = $this->putJson("/api/subcategorias-egreso/{$sub2->id}", [
            'nombre' => 'Gastos Bancarios',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * 11. Alternar estado activo / inactivo de una subcategoría (toggle).
     */
    public function test_administrador_desactiva_y_reactiva_subcategoria(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Campaña Publicitaria Temporal',
            'activo' => true,
        ]);

        // 1. Desactivar
        $respDesactivar = $this->patchJson("/api/subcategorias-egreso/{$subcategoria->id}/toggle-activo");
        $respDesactivar->assertStatus(200)
            ->assertJsonPath('message', 'Subcategoría de egreso desactivada correctamente.')
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('subcategorias_egreso', [
            'id'     => $subcategoria->id,
            'activo' => 0,
        ]);

        // 2. Reactivar
        $respReactivar = $this->patchJson("/api/subcategorias-egreso/{$subcategoria->id}/toggle-activo");
        $respReactivar->assertStatus(200)
            ->assertJsonPath('message', 'Subcategoría de egreso reactivada correctamente.')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('subcategorias_egreso', [
            'id'     => $subcategoria->id,
            'activo' => 1,
        ]);
    }

    /**
     * 12. Las subcategorías inactivas se excluyen del listado operativo (GET /api/subcategorias-egreso).
     */
    public function test_subcategoria_inactiva_se_excluye_del_listado_operativo_de_egresos(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Subcategoría En Desuso',
            'activo' => false,
        ]);

        Sanctum::actingAs($this->contadora);
        $response = $this->getJson('/api/subcategorias-egreso');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($subcategoria->id, $ids);
    }

    /**
     * 13. Intentar registrar un egreso con una subcategoría inactiva es rechazado con 422.
     */
    public function test_subcategoria_inactiva_no_permite_registrar_factura_de_egreso(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Subcategoría Retirada',
            'activo' => false,
        ]);

        Sanctum::actingAs($this->contadora);
        $response = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $subcategoria->id,
            'proveedor'              => 'Proveedor Prueba',
            'numero_comprobante'     => 'FAC-8899',
            'fecha_comprobante'      => now()->toDateString(),
            'monto_total'            => 113.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subcategoria_egreso_id']);
    }

    /**
     * 14. Desactivar una subcategoría conserva la integridad de los egresos históricos asociados.
     */
    public function test_subcategoria_desactivada_conserva_integridad_de_egresos_historicos(): void
    {
        $subcategoria = SubcategoriaEgreso::create([
            'nombre' => 'Mantenimiento Preventivo 2026',
            'activo' => true,
        ]);

        // Registrar egreso previo
        $egreso = Egreso::create([
            'subcategoria_egreso_id' => $subcategoria->id,
            'usuario_id'             => $this->contadora->id,
            'proveedor'              => 'Taller Central S.A.',
            'numero_comprobante'     => 'FAC-9911',
            'fecha_comprobante'      => now()->toDateString(),
            'monto_total'            => 150.00,
            'costo_neto'             => 132.74,
            'iva_credito_fiscal'     => 17.26,
            'correlativo'            => 'EG-000999',
        ]);

        // Desactivar la subcategoría desde el panel de configuración
        $this->patchJson("/api/subcategorias-egreso/{$subcategoria->id}/toggle-activo")
            ->assertStatus(200)
            ->assertJsonPath('data.activo', false);

        // El registro histórico del egreso continúa intacto y vinculado a la subcategoría
        $this->assertDatabaseHas('egresos', [
            'id'                     => $egreso->id,
            'subcategoria_egreso_id' => $subcategoria->id,
            'monto_total'            => '150.00',
        ]);

        $this->assertEquals($subcategoria->nombre, $egreso->fresh()->subcategoria->nombre);
    }

    /**
     * 15. Contrato estándar de API en respuestas del catálogo de subcategorías { status, message, data }.
     */
    public function test_contrato_api_en_respuestas_de_catalogo_subcategorias(): void
    {
        $response = $this->getJson('/api/subcategorias-egreso/todas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'nombre',
                        'activo',
                    ],
                ],
            ]);
    }
}
