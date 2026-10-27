<?php

namespace Tests\Feature\Api;

use App\Models\Auditoria;
use App\Models\Egreso;
use App\Models\SubcategoriaEgreso;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas integrales de carga rápida categorizada de egresos y facturas.
 *
 * @autor  Cesar
 * @fecha  2026-10-25
 * @módulo Contabilidad – US-CON-01 / RF-CON-005 / CC-61 / CC-62 / CC-63 / CC-64 / CC-65
 */
class CargaEgresoTest extends TestCase
{
    use RefreshDatabase;

    private User $contadora;

    private User $administrador;

    private User $socio;

    private User $mesero;

    private SubcategoriaEgreso $subcategoriaCarnes;

    private SubcategoriaEgreso $subcategoriaVerduras;

    /**
     * Prepara el entorno con roles oficiales, subcategorías del catálogo y usuarios autenticables.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-65
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, SubcategoriaEgresoSeeder::class]);

        $this->contadora = User::factory()->create(['name' => 'Contadora Test']);
        $this->contadora->assignRole('contadora');

        $this->administrador = User::factory()->create(['name' => 'Admin Test']);
        $this->administrador->assignRole('administrador');

        $this->socio = User::factory()->create(['name' => 'Socio Test']);
        $this->socio->assignRole('socio');

        $this->mesero = User::factory()->create(['name' => 'Mesero Test']);
        $this->mesero->assignRole('mesero');

        $this->subcategoriaCarnes = SubcategoriaEgreso::where('nombre', 'Carnes')->firstOrFail();
        $this->subcategoriaVerduras = SubcategoriaEgreso::where('nombre', 'Verduras y frutas')->firstOrFail();

        Sanctum::actingAs($this->contadora);
    }

    /**
     * Verifica que la contadora obtiene el catálogo de subcategorías activas ordenado alfabéticamente.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-61 / CC-62 / CC-65
     */
    public function test_contadora_obtiene_catalogo_de_subcategorias_activas_ordenadas_alfabeticamente(): void
    {
        $respuesta = $this->getJson('/api/subcategorias-egreso');

        $respuesta
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Subcategorías de egreso obtenidas correctamente.')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'nombre'],
                ],
            ]);

        $nombres = collect($respuesta->json('data'))->pluck('nombre')->all();
        $nombresEsperados = SubcategoriaEgreso::where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->all();

        $this->assertSame($nombresEsperados, $nombres);
        $this->assertContains('Carnes', $nombres);
        $this->assertContains('Aves', $nombres);
        $this->assertContains('Servicios básicos', $nombres);
    }

    /**
     * Verifica que el catálogo de subcategorías excluye elementos inactivos o dados de baja.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-62 / CC-65
     */
    public function test_catalogo_de_subcategorias_excluye_subcategorias_inactivas(): void
    {
        SubcategoriaEgreso::create([
            'nombre' => 'Subcategoría Desactivada',
            'activo' => false,
        ]);

        $respuesta = $this->getJson('/api/subcategorias-egreso');

        $respuesta->assertOk();
        $nombres = collect($respuesta->json('data'))->pluck('nombre');
        $this->assertFalse($nombres->contains('Subcategoría Desactivada'));
    }

    /**
     * Verifica que la consulta del catálogo de subcategorías requiere autenticación y rol autorizado.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-62 / CC-65
     */
    public function test_consulta_de_subcategorias_requiere_autenticacion_y_rol_autorizado(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/subcategorias-egreso')->assertUnauthorized();

        Sanctum::actingAs($this->mesero);
        $this->getJson('/api/subcategorias-egreso')->assertForbidden();

        Role::findOrCreate('cocinero', 'web');
        $cocinero = User::factory()->create();
        $cocinero->assignRole('cocinero');
        Sanctum::actingAs($cocinero);
        $this->getJson('/api/subcategorias-egreso')->assertForbidden();

        Role::findOrCreate('cajero', 'web');
        $cajero = User::factory()->create();
        $cajero->assignRole('cajero');
        Sanctum::actingAs($cajero);
        $this->getJson('/api/subcategorias-egreso')->assertForbidden();

        Sanctum::actingAs($this->administrador);
        $this->getJson('/api/subcategorias-egreso')->assertOk();

        Sanctum::actingAs($this->socio);
        $this->getJson('/api/subcategorias-egreso')->assertOk();
    }

    /**
     * Verifica que la contadora registra un egreso con correlativo automático y formato oficial EG-000001.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-64 / CC-65
     */
    public function test_contadora_registra_egreso_con_datos_validos_y_correlativo_automatico(): void
    {
        $payload = [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Distribuidora Carnes del Valle S.A.',
            'numero_comprobante' => 'CCF-001248',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 150.00,
            'descripcion' => 'Compra de 50 lb de lomo de aguja para fin de semana.',
        ];

        $respuesta = $this->postJson('/api/egresos', $payload);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Egreso EG-000001 registrado correctamente.')
            ->assertJsonPath('data.correlativo', 'EG-000001')
            ->assertJsonPath('data.proveedor', 'Distribuidora Carnes del Valle S.A.')
            ->assertJsonPath('data.numero_comprobante', 'CCF-001248')
            ->assertJsonPath('data.fecha_comprobante', '2026-10-24')
            ->assertJsonPath('data.monto_total', '150.00')
            ->assertJsonPath('data.subcategoria.id', $this->subcategoriaCarnes->id)
            ->assertJsonPath('data.subcategoria.nombre', 'Carnes');

        $this->assertDatabaseHas('egresos', [
            'correlativo' => 'EG-000001',
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'usuario_id' => $this->contadora->id,
            'proveedor' => 'Distribuidora Carnes del Valle S.A.',
            'numero_comprobante' => 'CCF-001248',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 150.00,
        ]);
    }

    /**
     * Verifica que los roles administrador y socio también tienen autorización para registrar egresos.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_administrador_y_socio_tambien_pueden_registrar_egresos(): void
    {
        Sanctum::actingAs($this->administrador);

        $respAdmin = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaVerduras->id,
            'proveedor' => 'Agrícola Central',
            'numero_comprobante' => 'FAC-7011',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 45.00,
        ]);

        $respAdmin->assertCreated()->assertJsonPath('data.correlativo', 'EG-000001');
        $this->assertDatabaseHas('egresos', [
            'correlativo' => 'EG-000001',
            'usuario_id' => $this->administrador->id,
        ]);

        Sanctum::actingAs($this->socio);

        $respSocio = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Avícola Santa Rita',
            'numero_comprobante' => 'FAC-8022',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 80.00,
        ]);

        $respSocio->assertCreated()->assertJsonPath('data.correlativo', 'EG-000002');
        $this->assertDatabaseHas('egresos', [
            'correlativo' => 'EG-000002',
            'usuario_id' => $this->socio->id,
        ]);
    }

    /**
     * Verifica que los correlativos se generan de forma secuencial con ceros a la izquierda.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-60 / CC-63 / CC-65
     */
    public function test_generacion_secuencial_de_correlativos_con_relleno_de_ceros(): void
    {
        $correlativos = [];

        for ($i = 1; $i <= 3; $i++) {
            $resp = $this->postJson('/api/egresos', [
                'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
                'proveedor' => "Proveedor {$i}",
                'numero_comprobante' => "FAC-00{$i}",
                'fecha_comprobante' => '2026-10-24',
                'monto_total' => 20.00 * $i,
            ]);

            $resp->assertCreated();
            $correlativos[] = $resp->json('data.correlativo');
        }

        $this->assertSame(['EG-000001', 'EG-000002', 'EG-000003'], $correlativos);
    }

    /**
     * Verifica que se calculan y persisten automáticamente el costo neto y el IVA crédito fiscal al 13%.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65 / US-CON-02
     */
    public function test_calcula_automaticamente_costo_neto_e_iva_credito_fiscal(): void
    {
        // $113.00 => Costo Neto $100.00 + IVA $13.00
        $resp1 = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Carnicería La Fama',
            'numero_comprobante' => 'FAC-113',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 113.00,
        ]);

        $resp1
            ->assertCreated()
            ->assertJsonPath('data.monto_total', '113.00')
            ->assertJsonPath('data.costo_neto', '100.00')
            ->assertJsonPath('data.iva_credito_fiscal', '13.00');

        $this->assertDatabaseHas('egresos', [
            'numero_comprobante' => 'FAC-113',
            'monto_total' => 113.00,
            'costo_neto' => 100.00,
            'iva_credito_fiscal' => 13.00,
        ]);

        // $56.50 => Costo Neto $50.00 + IVA $6.50
        $resp2 = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaVerduras->id,
            'proveedor' => 'Verdulería Don Pedro',
            'numero_comprobante' => 'FAC-565',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 56.50,
        ]);

        $resp2
            ->assertCreated()
            ->assertJsonPath('data.monto_total', '56.50')
            ->assertJsonPath('data.costo_neto', '50.00')
            ->assertJsonPath('data.iva_credito_fiscal', '6.50');
    }

    /**
     * Verifica la coherencia de redondeo de costo neto e IVA para montos fraccionarios.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_redondeo_exacto_de_iva_y_costo_neto_para_montos_fraccionarios(): void
    {
        // $25.00 => Costo neto: 25 / 1.13 = 22.12; IVA: 22.12 * 0.13 = 2.88; Total: 22.12 + 2.88 = 25.00
        $respuesta = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Pollo Campero Compras',
            'numero_comprobante' => 'FAC-025',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 25.00,
        ]);

        $respuesta
            ->assertCreated()
            ->assertJsonPath('data.monto_total', '25.00')
            ->assertJsonPath('data.costo_neto', '22.12')
            ->assertJsonPath('data.iva_credito_fiscal', '2.88');
    }

    /**
     * Verifica que cada registro de egreso genera su correspondiente asiento en la bitácora de auditoría.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65 / RF-ADM-008
     */
    public function test_registro_de_egreso_genera_bitacora_de_auditoria(): void
    {
        $respuesta = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Lácteos El Carmen',
            'numero_comprobante' => 'FAC-9921',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 60.00,
        ]);

        $respuesta->assertCreated();
        $egresoId = $respuesta->json('data.id');

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->contadora->id,
            'accion' => 'egreso_registrado',
            'entidad_type' => Egreso::class,
            'entidad_id' => $egresoId,
        ]);
    }

    /**
     * Verifica que la solicitud rechaza peticiones con campos obligatorios vacíos o faltantes.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_rechaza_registro_con_campos_obligatorios_faltantes(): void
    {
        $respuesta = $this->postJson('/api/egresos', []);

        $respuesta
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subcategoria_egreso_id',
                'proveedor',
                'numero_comprobante',
                'fecha_comprobante',
                'monto_total',
            ]);

        $this->assertDatabaseCount('egresos', 0);
    }

    /**
     * Verifica que no se permite duplicar el mismo número de comprobante para el mismo proveedor.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-60 / CC-63 / CC-65
     */
    public function test_rechaza_comprobante_duplicado_para_el_mismo_proveedor(): void
    {
        $payload = [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'La Constancia S.A.',
            'numero_comprobante' => 'FAC-000456',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 120.00,
        ];

        $this->postJson('/api/egresos', $payload)->assertCreated();

        $respuestaDuplicada = $this->postJson('/api/egresos', $payload);

        $respuestaDuplicada
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_comprobante');

        $this->assertSame(
            'Esta factura de este proveedor ya fue registrada.',
            $respuestaDuplicada->json('errors.numero_comprobante.0')
        );

        $this->assertDatabaseCount('egresos', 1);
    }

    /**
     * Verifica que se permite el mismo número de comprobante si los proveedores son distintos.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-60 / CC-63 / CC-65
     */
    public function test_permite_mismo_numero_de_comprobante_para_proveedores_distintos(): void
    {
        $resp1 = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Proveedor Norte S.A.',
            'numero_comprobante' => 'FAC-001',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 50.00,
        ]);
        $resp1->assertCreated();

        $resp2 = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaVerduras->id,
            'proveedor' => 'Proveedor Sur S.A.',
            'numero_comprobante' => 'FAC-001',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 75.00,
        ]);
        $resp2->assertCreated();

        $this->assertDatabaseCount('egresos', 2);
    }

    /**
     * Verifica que se rechazan fechas futuras o que no cumplan el formato AAAA-MM-DD.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_rechaza_fechas_futuras_o_con_formato_invalido(): void
    {
        // Fecha futura
        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Gas del Pacífico',
            'numero_comprobante' => 'FAC-901',
            'fecha_comprobante' => now()->addDays(2)->toDateString(),
            'monto_total' => 35.00,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_comprobante');

        // Formato inválido DD/MM/AAAA
        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Gas del Pacífico',
            'numero_comprobante' => 'FAC-902',
            'fecha_comprobante' => '24-10-2026',
            'monto_total' => 35.00,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_comprobante');
    }

    /**
     * Verifica validación de montos: rechaza cero, negativos, exceso de decimales y valores desmedidos.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_rechaza_montos_invalidos_cero_negativos_o_con_exceso_de_decimales(): void
    {
        $base = [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Distribuidora Central',
            'numero_comprobante' => 'FAC-010',
            'fecha_comprobante' => '2026-10-24',
        ];

        // Monto cero
        $this->postJson('/api/egresos', array_merge($base, ['monto_total' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto_total');

        // Monto negativo
        $this->postJson('/api/egresos', array_merge($base, ['monto_total' => -15.50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto_total');

        // Más de dos decimales
        $this->postJson('/api/egresos', array_merge($base, ['monto_total' => 12.345]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto_total');

        // Excede máximo de 99,999,999.99
        $this->postJson('/api/egresos', array_merge($base, ['monto_total' => 100000000.00]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto_total');

        // No numérico
        $this->postJson('/api/egresos', array_merge($base, ['monto_total' => 'cien']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('monto_total');
    }

    /**
     * Verifica que rechaza subcategorías inexistentes o que se encuentran desactivadas.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-62 / CC-63 / CC-65
     */
    public function test_rechaza_subcategoria_inexistente_o_inactiva(): void
    {
        $base = [
            'proveedor' => 'Distribuidora Central',
            'numero_comprobante' => 'FAC-011',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 50.00,
        ];

        // Subcategoría inexistente
        $this->postJson('/api/egresos', array_merge($base, ['subcategoria_egreso_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subcategoria_egreso_id');

        // Subcategoría inactiva
        $inactiva = SubcategoriaEgreso::create([
            'nombre' => 'Subcategoría Deshabilitada',
            'activo' => false,
        ]);

        $this->postJson('/api/egresos', array_merge($base, ['subcategoria_egreso_id' => $inactiva->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subcategoria_egreso_id');
    }

    /**
     * Verifica que la descripción es opcional y no puede superar los 500 caracteres.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_descripcion_es_opcional_pero_valida_longitud_maxima(): void
    {
        // Sin descripción: se guarda exitosamente
        $resp = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Carnicería Sin Detalle',
            'numero_comprobante' => 'FAC-991',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 40.00,
        ]);
        $resp->assertCreated();
        $this->assertDatabaseHas('egresos', [
            'numero_comprobante' => 'FAC-991',
            'descripcion' => null,
        ]);

        // Descripción de más de 500 caracteres
        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Carnicería Con Exceso',
            'numero_comprobante' => 'FAC-992',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 40.00,
            'descripcion' => str_repeat('A', 501),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('descripcion');
    }

    /**
     * Verifica que los espacios en blanco sobrantes en proveedor y comprobante se limpian con trim.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_trimea_espacios_en_blanco_en_proveedor_y_comprobante(): void
    {
        $resp = $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => '   Avícola Campestre S.A.   ',
            'numero_comprobante' => '   F-44812   ',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 70.00,
        ]);

        $resp->assertCreated();

        $this->assertDatabaseHas('egresos', [
            'proveedor' => 'Avícola Campestre S.A.',
            'numero_comprobante' => 'F-44812',
        ]);
    }

    /**
     * Verifica que usuarios sin autenticación o con roles no autorizados son rechazados al registrar egresos.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-63 / CC-65
     */
    public function test_seguridad_rbac_bloquea_a_usuarios_no_autenticados_y_roles_no_autorizados(): void
    {
        $payload = [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Distribuidora Central',
            'numero_comprobante' => 'FAC-999',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 50.00,
        ];

        // Sin sesión
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/egresos', $payload)->assertUnauthorized();

        // Mesero
        Sanctum::actingAs($this->mesero);
        $this->postJson('/api/egresos', $payload)->assertForbidden();

        // Cajero
        Role::findOrCreate('cajero', 'web');
        $cajero = User::factory()->create();
        $cajero->assignRole('cajero');
        Sanctum::actingAs($cajero);
        $this->postJson('/api/egresos', $payload)->assertForbidden();

        // Cocinero
        Role::findOrCreate('cocinero', 'web');
        $cocinero = User::factory()->create();
        $cocinero->assignRole('cocinero');
        Sanctum::actingAs($cocinero);
        $this->postJson('/api/egresos', $payload)->assertForbidden();
    }

    /**
     * Verifica que la contadora puede consultar el historial con filtros de fecha y subcategoría.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-65 / US-CON-02 / CC-69
     */
    public function test_contadora_consulta_historial_de_egresos_con_totales_y_paginacion(): void
    {
        // Registrar tres egresos en fechas y subcategorías distintas
        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Carnes Don Julio',
            'numero_comprobante' => 'FAC-101',
            'fecha_comprobante' => '2026-10-20',
            'monto_total' => 113.00,
        ])->assertCreated();

        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaCarnes->id,
            'proveedor' => 'Carnes Don Julio',
            'numero_comprobante' => 'FAC-102',
            'fecha_comprobante' => '2026-10-22',
            'monto_total' => 113.00,
        ])->assertCreated();

        $this->postJson('/api/egresos', [
            'subcategoria_egreso_id' => $this->subcategoriaVerduras->id,
            'proveedor' => 'Verduras El Huerto',
            'numero_comprobante' => 'FAC-201',
            'fecha_comprobante' => '2026-10-24',
            'monto_total' => 56.50,
        ])->assertCreated();

        // 1. Consulta general
        $respGeneral = $this->getJson('/api/egresos');
        $respGeneral
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', 'Historial de egresos obtenido.')
            ->assertJsonPath('data.totales.cantidad', 3)
            ->assertJsonPath('data.totales.monto_total', fn ($v) => (float) $v === 282.50)
            ->assertJsonPath('data.totales.costo_neto', fn ($v) => (float) $v === 250.00)
            ->assertJsonPath('data.totales.iva_credito_fiscal', fn ($v) => (float) $v === 32.50)
            ->assertJsonCount(3, 'data.egresos.data');

        // 2. Filtro por subcategoría Carnes
        $respFiltroSub = $this->getJson("/api/egresos?subcategoria_egreso_id={$this->subcategoriaCarnes->id}");
        $respFiltroSub
            ->assertOk()
            ->assertJsonPath('data.totales.cantidad', 2)
            ->assertJsonPath('data.totales.monto_total', fn ($v) => (float) $v === 226.00)
            ->assertJsonCount(2, 'data.egresos.data');

        // 3. Filtro por rango de fecha
        $respFiltroFecha = $this->getJson('/api/egresos?desde=2026-10-21&hasta=2026-10-23');
        $respFiltroFecha
            ->assertOk()
            ->assertJsonPath('data.totales.cantidad', 1)
            ->assertJsonPath('data.totales.monto_total', fn ($v) => (float) $v === 113.00)
            ->assertJsonCount(1, 'data.egresos.data');
    }

    /**
     * Verifica que el historial de egresos bloquea accesos no autorizados y sin sesión.
     *
     * @autor  Cesar
     * @fecha  2026-10-25
     * @módulo Contabilidad – US-CON-01 / CC-65 / US-CON-02 / CC-69
     */
    public function test_historial_de_egresos_bloquea_a_roles_no_autorizados(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/egresos')->assertUnauthorized();

        Sanctum::actingAs($this->mesero);
        $this->getJson('/api/egresos')->assertForbidden();
    }
}

