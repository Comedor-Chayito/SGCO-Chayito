<?php

namespace Tests\Feature\Api;

use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\User;
use App\Services\ComandaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del panel de configuración de platillos (US-ADM-03 / CC-104 / CC-106 / CC-112).
 * Cubre CRUD, activación en el menú del día, RBAC (403), validación de precio
 * DECIMAL(10,2) y la integridad del histórico de comandas ante cambios de precio.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – RF-ADM-008 / CC-112
 */
class PlatilloTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrador;

    /**
     * Prepara los roles oficiales y autentica a un administrador por defecto.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'socio', 'mesero', 'cajero', 'cocinero', 'contadora'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }

        $this->administrador = $this->usuarioConRol('administrador');
        Sanctum::actingAs($this->administrador);
    }

    /**
     * Crea un usuario con el rol indicado.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @param  string  $rol  Nombre del rol a asignar.
     * @return User Usuario creado.
     */
    private function usuarioConRol(string $rol): User
    {
        $usuario = User::factory()->create(['activo' => true]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * Crea un platillo de prueba.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @param  array<string, mixed>  $atributos  Atributos a sobrescribir.
     * @return Platillo Platillo creado.
     */
    private function crearPlatillo(array $atributos = []): Platillo
    {
        return Platillo::create(array_merge([
            'nombre' => 'Pollo Asado',
            'descripcion' => 'Pollo al carbón',
            'precio_unitario' => 2.50,
            'disponible' => true,
        ], $atributos));
    }

    /**
     * Registra una comanda de un platillo con el cajero (flujo real del POS).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @param  Platillo  $platillo  Platillo a comandar.
     * @param  int  $cantidad  Unidades pedidas.
     * @return Comanda Comanda registrada.
     */
    private function registrarComanda(Platillo $platillo, int $cantidad = 2): Comanda
    {
        $mesa = Mesa::firstOrCreate(['numero' => 1], ['capacidad' => 4, 'estado' => 'libre']);
        Sanctum::actingAs($this->usuarioConRol('cajero'));

        $respuesta = $this->postJson('/api/comandas', [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => $cantidad]],
        ])->assertCreated();

        Sanctum::actingAs($this->administrador);

        return Comanda::findOrFail($respuesta->json('data.id'));
    }

    // ── RBAC ────────────────────────────────────────────────────────────────

    /**
     * Los roles distintos de administrador y socio reciben 403 en todos los endpoints.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_roles_no_administrativos_reciben_403_en_todos_los_endpoints(): void
    {
        $platillo = $this->crearPlatillo();
        $payload = ['nombre' => 'Nuevo', 'precio_unitario' => 3.00];

        foreach (['mesero', 'cajero', 'cocinero', 'contadora'] as $rol) {
            Sanctum::actingAs($this->usuarioConRol($rol));

            $this->getJson('/api/platillos/catalogo')->assertForbidden();
            $this->postJson('/api/platillos', $payload)->assertForbidden();
            $this->getJson("/api/platillos/{$platillo->id}")->assertForbidden();
            $this->putJson("/api/platillos/{$platillo->id}", $payload)->assertForbidden();
            $this->deleteJson("/api/platillos/{$platillo->id}")->assertForbidden();
            $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('platillos', [
            'id' => $platillo->id,
            'nombre' => 'Pollo Asado',
            'precio_unitario' => 2.50,
            'disponible' => true,
        ]);
        $this->assertDatabaseCount('platillos', 1);
    }

    /**
     * Sin token no hay acceso al catálogo administrativo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_sin_autenticacion_recibe_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/platillos/catalogo')->assertUnauthorized();
        $this->postJson('/api/platillos', ['nombre' => 'X', 'precio_unitario' => 1])->assertUnauthorized();
    }

    /**
     * El rol socio tiene el mismo acceso que el administrador.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_socio_puede_gestionar_platillos(): void
    {
        Sanctum::actingAs($this->usuarioConRol('socio'));

        $this->postJson('/api/platillos', ['nombre' => 'Sopa', 'precio_unitario' => 3.25])
            ->assertCreated()
            ->assertJsonPath('status', 'ok');

        $this->getJson('/api/platillos/catalogo')->assertOk()->assertJsonCount(1, 'data');
    }

    // ── CRUD (CC-104) ───────────────────────────────────────────────────────

    /**
     * Crear un platillo devuelve 201 con el contrato { status, message, data }.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_administrador_crea_platillo_con_contrato_estandar(): void
    {
        $this->postJson('/api/platillos', [
            'nombre' => '  Sopa de Res  ',
            'descripcion' => 'Sopa del día',
            'precio_unitario' => 4.5,
        ])
            ->assertCreated()
            ->assertJsonStructure(['status', 'message', 'data' => ['id', 'nombre', 'precio_unitario']])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.nombre', 'Sopa de Res')
            ->assertJsonPath('data.precio_unitario', '4.50')
            ->assertJsonPath('data.disponible', true);

        $this->assertDatabaseHas('platillos', [
            'nombre' => 'Sopa de Res',
            'precio_unitario' => 4.50,
            'disponible' => true,
        ]);
    }

    /**
     * El catálogo administrativo lista disponibles y no disponibles, y filtra.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_catalogo_lista_todos_los_platillos_y_permite_filtrar(): void
    {
        $this->crearPlatillo(['nombre' => 'Pollo Asado']);
        $this->crearPlatillo(['nombre' => 'Pescado Frito', 'disponible' => false]);

        $this->getJson('/api/platillos/catalogo')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/platillos/catalogo?disponible=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Pescado Frito');

        $this->getJson('/api/platillos/catalogo?busqueda=pollo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Pollo Asado');
    }

    /**
     * Mostrar devuelve el detalle y un id inexistente responde 404.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_mostrar_platillo_y_404_si_no_existe(): void
    {
        $platillo = $this->crearPlatillo();

        $this->getJson("/api/platillos/{$platillo->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Pollo Asado');

        $this->getJson('/api/platillos/9999')->assertNotFound();
    }

    /**
     * Actualizar modifica nombre, descripción y precio.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_administrador_actualiza_platillo(): void
    {
        $platillo = $this->crearPlatillo();

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre' => 'Pollo Asado Familiar',
            'descripcion' => null,
            'precio_unitario' => 3.75,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.precio_unitario', '3.75');

        $this->assertDatabaseHas('platillos', [
            'id' => $platillo->id,
            'nombre' => 'Pollo Asado Familiar',
            'descripcion' => null,
            'precio_unitario' => 3.75,
        ]);
    }

    /**
     * Actualizar conservando el mismo nombre no dispara la regla de unicidad.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_actualizar_con_el_mismo_nombre_es_valido(): void
    {
        $platillo = $this->crearPlatillo();

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre' => 'Pollo Asado',
            'precio_unitario' => 2.75,
        ])->assertOk();
    }

    /**
     * No se permiten dos platillos con el mismo nombre.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_nombre_duplicado_es_rechazado(): void
    {
        $this->crearPlatillo(['nombre' => 'Pollo Asado']);
        $otro = $this->crearPlatillo(['nombre' => 'Carne Guisada']);

        $this->postJson('/api/platillos', ['nombre' => 'Pollo Asado', 'precio_unitario' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        $this->putJson("/api/platillos/{$otro->id}", ['nombre' => 'Pollo Asado', 'precio_unitario' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    /**
     * Eliminar un platillo sin historial ni receta lo borra.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_elimina_platillo_sin_historial(): void
    {
        $platillo = $this->crearPlatillo();

        $this->deleteJson("/api/platillos/{$platillo->id}")
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('platillos', ['id' => $platillo->id]);
    }

    /**
     * Un platillo con comandas no se elimina (422) y su histórico se conserva.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_no_elimina_platillo_con_comandas_registradas(): void
    {
        $platillo = $this->crearPlatillo();
        $comanda = $this->registrarComanda($platillo);

        $this->deleteJson("/api/platillos/{$platillo->id}")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id]);
        $this->assertDatabaseHas('comanda_detalles', ['comanda_id' => $comanda->id]);
    }

    /**
     * Un platillo con receta vinculada no se elimina (422).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_no_elimina_platillo_con_receta_vinculada(): void
    {
        $platillo = $this->crearPlatillo();
        $insumo = Insumo::create([
            'nombre' => 'Pollo',
            'unidad_medida' => 'kg',
            'stock_actual' => 0,
            'stock_minimo' => 0,
        ]);
        Receta::create([
            'platillo_id' => $platillo->id,
            'insumo_id' => $insumo->id,
            'cantidad_por_porcion' => 0.25,
            'unidad_consumo' => 'kg',
        ]);

        $this->deleteJson("/api/platillos/{$platillo->id}")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id]);
    }

    // ── Validación de precio DECIMAL(10,2) ──────────────────────────────────

    /**
     * El precio debe ser mayor que cero, numérico, con máximo 2 decimales y dentro de DECIMAL(10,2).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @param  mixed  $precio  Valor inválido a enviar.
     * @return void
     */
    #[DataProvider('preciosInvalidos')]
    public function test_precio_invalido_es_rechazado_al_crear_y_actualizar(mixed $precio): void
    {
        $platillo = $this->crearPlatillo();

        $this->postJson('/api/platillos', ['nombre' => 'Otro', 'precio_unitario' => $precio])
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio_unitario');

        $this->putJson("/api/platillos/{$platillo->id}", ['nombre' => 'Pollo Asado', 'precio_unitario' => $precio])
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio_unitario');

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id, 'precio_unitario' => 2.50]);
        $this->assertDatabaseCount('platillos', 1);
    }

    /**
     * Valores de precio que deben fallar.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return array<string, array<int, mixed>>
     */
    public static function preciosInvalidos(): array
    {
        return [
            'cero' => [0],
            'negativo' => [-1.5],
            'tres decimales' => [2.505],
            'texto' => ['abc'],
            'excede decimal(10,2)' => [100000000],
            'vacío' => [''],
            'nulo' => [null],
        ];
    }

    /**
     * Un precio mínimo válido (0.01) y el máximo de DECIMAL(10,2) se aceptan.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_precio_en_los_limites_validos_es_aceptado(): void
    {
        $this->postJson('/api/platillos', ['nombre' => 'Mínimo', 'precio_unitario' => 0.01])
            ->assertCreated()
            ->assertJsonPath('data.precio_unitario', '0.01');

        $this->postJson('/api/platillos', ['nombre' => 'Máximo', 'precio_unitario' => 99999999.99])
            ->assertCreated()
            ->assertJsonPath('data.precio_unitario', '99999999.99');
    }

    /**
     * El nombre es obligatorio y se limita a 100 caracteres.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_nombre_es_obligatorio_y_limitado(): void
    {
        $this->postJson('/api/platillos', ['precio_unitario' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');

        $this->postJson('/api/platillos', ['nombre' => str_repeat('a', 101), 'precio_unitario' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    // ── Disponibilidad (CC-106) ─────────────────────────────────────────────

    /**
     * Desactivar y reactivar un platillo del menú del día.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_desactiva_y_reactiva_platillo(): void
    {
        $platillo = $this->crearPlatillo();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.disponible', false);
        $this->assertDatabaseHas('platillos', ['id' => $platillo->id, 'disponible' => false]);

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => true])
            ->assertOk()
            ->assertJsonPath('data.disponible', true);
        $this->assertDatabaseHas('platillos', ['id' => $platillo->id, 'disponible' => true]);
    }

    /**
     * La disponibilidad es obligatoria y booleana; el PUT no permite cambiarla.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_disponibilidad_se_valida_y_no_se_cambia_por_put(): void
    {
        $platillo = $this->crearPlatillo();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('disponible');

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => 'tal vez'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('disponible');

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre' => 'Pollo Asado',
            'precio_unitario' => 2.50,
            'disponible' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('disponible');

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id, 'disponible' => true]);
    }

    /**
     * Un platillo desactivado desaparece del POS y no admite nuevas comandas; reactivarlo lo devuelve.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_platillo_desactivado_no_aparece_en_pos_ni_admite_comandas(): void
    {
        $activo = $this->crearPlatillo(['nombre' => 'Carne Guisada']);
        $platillo = $this->crearPlatillo(['nombre' => 'Pollo Asado']);
        $mesa = Mesa::create(['numero' => 5, 'capacidad' => 4, 'estado' => 'libre']);

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])->assertOk();

        $this->getJson('/api/platillos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activo->id);

        $this->postJson('/api/comandas', [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.platillo_id');

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => true])->assertOk();

        $this->getJson('/api/platillos')->assertOk()->assertJsonCount(2, 'data');
    }

    /**
     * Desactivar un platillo conserva sus comandas históricas intactas.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_desactivar_platillo_conserva_el_historico(): void
    {
        $platillo = $this->crearPlatillo();
        $comanda = $this->registrarComanda($platillo, 2);

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])->assertOk();

        $this->assertDatabaseHas('platillos', ['id' => $platillo->id, 'disponible' => false]);
        $this->assertDatabaseHas('comanda_detalles', [
            'comanda_id' => $comanda->id,
            'platillo_id' => $platillo->id,
            'cantidad' => 2,
            'precio_unitario' => 2.50,
        ]);
        $this->assertDatabaseHas('comandas', ['id' => $comanda->id, 'subtotal' => 5.00]);
    }

    // ── Precios e histórico ─────────────────────────────────────────────────

    /**
     * Un cambio de precio se refleja en el POS pero no altera comandas ya registradas.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_cambio_de_precio_se_refleja_en_pos_sin_alterar_historico(): void
    {
        $platillo = $this->crearPlatillo();
        $comandaAnterior = $this->registrarComanda($platillo, 2);

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre' => 'Pollo Asado',
            'descripcion' => 'Pollo al carbón',
            'precio_unitario' => 4.00,
        ])->assertOk();

        // El POS ya muestra el precio nuevo.
        $this->getJson('/api/platillos')
            ->assertOk()
            ->assertJsonPath('data.0.precio_unitario', '4.00');

        // La comanda anterior conserva precio de línea y subtotal.
        $this->assertDatabaseHas('comanda_detalles', [
            'comanda_id' => $comandaAnterior->id,
            'precio_unitario' => 2.50,
        ]);
        $this->assertDatabaseHas('comandas', ['id' => $comandaAnterior->id, 'subtotal' => 5.00]);

        // Una comanda nueva usa el precio vigente.
        $comandaNueva = $this->registrarComanda($platillo, 2);

        $this->assertDatabaseHas('comanda_detalles', [
            'comanda_id' => $comandaNueva->id,
            'precio_unitario' => 4.00,
        ]);
        $this->assertDatabaseHas('comandas', ['id' => $comandaNueva->id, 'subtotal' => 8.00]);

        // Releer la comanda anterior desde la API tampoco cambia.
        $this->getJson('/api/comandas')
            ->assertOk()
            ->assertJsonFragment(['id' => $comandaAnterior->id, 'subtotal' => '5.00']);
    }

    // ── Correcciones de la auditoría ────────────────────────────────────────

    /**
     * Filtros malformados se rechazan con 422 en lugar de provocar un 500 o ignorarse.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_filtros_malformados_del_catalogo_responden_422(): void
    {
        $this->crearPlatillo();

        $this->getJson('/api/platillos/catalogo?busqueda[]=arroz')
            ->assertStatus(422)
            ->assertJsonValidationErrors('busqueda');

        $this->getJson('/api/platillos/catalogo?disponible=basura')
            ->assertStatus(422)
            ->assertJsonValidationErrors('disponible');

        $this->getJson('/api/platillos/catalogo?busqueda='.str_repeat('a', 101))
            ->assertStatus(422)
            ->assertJsonValidationErrors('busqueda');

        $this->getJson('/api/platillos/catalogo?disponible=1&busqueda=pollo')->assertOk();
    }

    /**
     * Un rol no autorizado recibe 403 también sobre ids inexistentes (no 404).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_rol_no_autorizado_recibe_403_aunque_el_id_no_exista(): void
    {
        Sanctum::actingAs($this->usuarioConRol('mesero'));
        $payload = ['nombre' => 'X', 'precio_unitario' => 1];

        $this->getJson('/api/platillos/9999')->assertForbidden();
        $this->putJson('/api/platillos/9999', $payload)->assertForbidden();
        $this->deleteJson('/api/platillos/9999')->assertForbidden();
        $this->patchJson('/api/platillos/9999/disponibilidad', ['disponible' => false])->assertForbidden();

        // El administrador sigue recibiendo 404 por un id inexistente.
        Sanctum::actingAs($this->administrador);
        $this->getJson('/api/platillos/9999')->assertNotFound();
    }

    /**
     * Un total que no cabe en comandas.subtotal DECIMAL(10,2) responde 422 y no deja registros.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_comanda_con_total_fuera_de_rango_responde_422_sin_dejar_registros(): void
    {
        $platillo = $this->crearPlatillo(['precio_unitario' => 60000000.00]);
        $mesa = Mesa::create(['numero' => 3, 'capacidad' => 4, 'estado' => 'libre']);
        Sanctum::actingAs($this->usuarioConRol('cajero'));

        $this->postJson('/api/comandas', [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 2]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('comandas', 0);
        $this->assertDatabaseCount('comanda_detalles', 0);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'estado' => 'libre']);

        // Una unidad sí cabe.
        $this->postJson('/api/comandas', [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 1]],
        ])->assertCreated();
    }

    /**
     * Si el platillo se desactiva entre la validación y el registro, el servicio lo rechaza.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_servicio_rechaza_platillo_desactivado_despues_de_validar(): void
    {
        $platillo = $this->crearPlatillo();
        $solicitud = RegistrarComandaRequest::create('/api/comandas', 'POST', [
            'canal' => 'para_llevar',
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 1]],
        ]);
        $solicitud->setContainer($this->app)->setRedirector($this->app['redirect']);
        $solicitud->validateResolved();

        $this->patchJson("/api/platillos/{$platillo->id}/disponibilidad", ['disponible' => false])->assertOk();

        try {
            $this->app->make(ComandaService::class)->registrar($solicitud, $this->administrador->id);
            $this->fail('Se registró una comanda con un platillo desactivado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.platillo_id', $e->errors());
        }

        $this->assertDatabaseCount('comandas', 0);
        $this->assertDatabaseCount('comanda_detalles', 0);
    }

    /**
     * Si el precio vigente difiere del que el operador confirmó, se rechaza sin crear la comanda.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_comanda_con_precio_esperado_distinto_al_vigente_se_rechaza(): void
    {
        $platillo = $this->crearPlatillo(['precio_unitario' => 4.75]);
        $mesa = Mesa::create(['numero' => 4, 'capacidad' => 4, 'estado' => 'libre']);
        Sanctum::actingAs($this->usuarioConRol('cajero'));
        $payload = fn (float $esperado) => [
            'canal' => 'mesa',
            'mesa_id' => $mesa->id,
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 1, 'precio_esperado' => $esperado]],
        ];

        $this->postJson('/api/comandas', $payload(2.50))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.precio_esperado');

        $this->assertDatabaseCount('comandas', 0);
        $this->assertDatabaseCount('comanda_detalles', 0);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'estado' => 'libre']);

        // Con el precio vigente confirmado sí se registra, al precio del servidor.
        $this->postJson('/api/comandas', $payload(4.75))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '4.75');
    }

    /**
     * El precio puede cambiar entre la validación y el registro: el servicio lo detecta bajo bloqueo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-112
     *
     * @return void
     */
    public function test_servicio_rechaza_precio_que_cambio_despues_de_validar(): void
    {
        $platillo = $this->crearPlatillo(['precio_unitario' => 2.50]);
        $solicitud = RegistrarComandaRequest::create('/api/comandas', 'POST', [
            'canal' => 'para_llevar',
            'items' => [['platillo_id' => $platillo->id, 'cantidad' => 1, 'precio_esperado' => 2.50]],
        ]);
        $solicitud->setContainer($this->app)->setRedirector($this->app['redirect']);
        $solicitud->validateResolved();

        $this->putJson("/api/platillos/{$platillo->id}", [
            'nombre' => 'Pollo Asado',
            'precio_unitario' => 4.75,
        ])->assertOk();

        try {
            $this->app->make(ComandaService::class)->registrar($solicitud, $this->administrador->id);
            $this->fail('Se registró una comanda con un precio distinto al confirmado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.precio_esperado', $e->errors());
        }

        $this->assertDatabaseCount('comandas', 0);
        $this->assertDatabaseCount('comanda_detalles', 0);
    }
}
