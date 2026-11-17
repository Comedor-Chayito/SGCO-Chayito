<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas del endpoint para consultar la matriz rol × permiso (US-ADM-04 / RF-ADM-008 / CC-121).
 * Verifica la disponibilidad del endpoint, control de acceso RBAC, estructura bidimensional
 * de la matriz y coherencia con los permisos asignados por Spatie.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-16
 * @módulo Administración y Seguridad – RF-ADM-008 (CC-121)
 */
class MatrizPermisoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $socio;
    protected User $cajero;
    protected User $mesero;
    protected User $cocinero;
    protected User $contadora;

    /**
     * Configuración inicial del entorno de prueba con roles y usuarios oficiales.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ejecutar sembrado oficial de roles y permisos
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();

        $this->socio = User::factory()->create([
            'name'   => 'Socio Auditor',
            'email'  => 'socio.auditor@sgco-chayito.local',
            'activo' => true,
        ]);
        $this->socio->assignRole('socio');

        $this->cocinero = User::factory()->create([
            'name'   => 'Cocinero Jefe',
            'email'  => 'cocinero.jefe@sgco-chayito.local',
            'activo' => true,
        ]);
        $this->cocinero->assignRole('cocinero');

        $this->contadora = User::factory()->create([
            'name'   => 'Contadora General',
            'email'  => 'contadora.general@sgco-chayito.local',
            'activo' => true,
        ]);
        $this->contadora->assignRole('contadora');
    }

    /**
     * Verifica que un administrador autenticado pueda consultar exitosamente la matriz rol × permiso.
     */
    public function test_administrador_puede_consultar_la_matriz_rol_permiso(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/matriz-permisos');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'roles',
                    'permisos',
                    'modulos',
                    'matriz',
                    'total_roles',
                    'total_permisos',
                ],
            ])
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Matriz de roles y permisos obtenida correctamente.',
            ]);

        $data = $response->json('data');

        $this->assertGreaterThanOrEqual(7, $data['total_roles']);
        $this->assertGreaterThanOrEqual(12, $data['total_permisos']);
        $this->assertIsArray($data['roles']);
        $this->assertIsArray($data['permisos']);
        $this->assertIsArray($data['modulos']);
        $this->assertIsArray($data['matriz']);
    }

    /**
     * Verifica que la ruta alternativa /api/roles/matriz responda idénticamente con la matriz.
     */
    public function test_ruta_alternativa_roles_matriz_responde_correctamente(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/roles/matriz');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Matriz de roles y permisos obtenida correctamente.',
            ]);
    }

    /**
     * Verifica que el rol socio conserve acceso a la consulta de la matriz de permisos.
     */
    public function test_socio_puede_consultar_la_matriz_rol_permiso(): void
    {
        Sanctum::actingAs($this->socio);

        $response = $this->getJson('/api/matriz-permisos');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    /**
     * Verifica que roles operativos no autorizados (cajero, mesero, cocinero, contadora) reciban HTTP 403 Forbidden.
     */
    public function test_roles_operativos_son_bloqueados_con_403_forbidden(): void
    {
        $rolesOperativos = [
            $this->cajero,
            $this->mesero,
            $this->cocinero,
            $this->contadora,
        ];

        foreach ($rolesOperativos as $usuario) {
            Sanctum::actingAs($usuario);

            $response = $this->getJson('/api/matriz-permisos');

            $response->assertStatus(403);
        }
    }

    /**
     * Verifica que un usuario sin autenticación reciba HTTP 401 Unauthorized.
     */
    public function test_usuario_no_autenticado_es_rechazado_con_401(): void
    {
        $response = $this->getJson('/api/matriz-permisos');

        $response->assertStatus(401);
    }

    /**
     * Verifica que la matriz bidimensional refleje con exactitud matemática las asignaciones oficiales de permisos.
     */
    public function test_matriz_bidimensional_refleja_asignaciones_oficiales(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/matriz-permisos');
        $matriz = $response->json('data.matriz');

        // Administrador tiene acceso total a todos los permisos
        foreach (['usuarios.ver', 'usuarios.crear', 'comandas.crear', 'cobros.procesar', 'inventario.gestionar', 'contabilidad.ver', 'reportes.ver'] as $permiso) {
            $this->assertTrue($matriz['administrador'][$permiso], "El administrador debe tener {$permiso}");
        }

        // Cajero solo tiene comandas.ver, cobros.ver y cobros.procesar
        $this->assertTrue($matriz['cajero']['comandas.ver']);
        $this->assertTrue($matriz['cajero']['cobros.ver']);
        $this->assertTrue($matriz['cajero']['cobros.procesar']);
        $this->assertFalse($matriz['cajero']['usuarios.crear']);
        $this->assertFalse($matriz['cajero']['inventario.gestionar']);
        $this->assertFalse($matriz['cajero']['contabilidad.ver']);
        $this->assertFalse($matriz['cajero']['reportes.ver']);

        // Mesero solo tiene comandas.ver y comandas.crear
        $this->assertTrue($matriz['mesero']['comandas.ver']);
        $this->assertTrue($matriz['mesero']['comandas.crear']);
        $this->assertFalse($matriz['mesero']['cobros.procesar']);
        $this->assertFalse($matriz['mesero']['usuarios.ver']);
        $this->assertFalse($matriz['mesero']['contabilidad.ver']);

        // Cocinero solo tiene comandas.ver, inventario.ver e inventario.gestionar
        $this->assertTrue($matriz['cocinero']['comandas.ver']);
        $this->assertTrue($matriz['cocinero']['inventario.ver']);
        $this->assertTrue($matriz['cocinero']['inventario.gestionar']);
        $this->assertFalse($matriz['cocinero']['cobros.procesar']);
        $this->assertFalse($matriz['cocinero']['usuarios.editar']);

        // Contadora solo tiene contabilidad.ver y reportes.ver
        $this->assertTrue($matriz['contadora']['contabilidad.ver']);
        $this->assertTrue($matriz['contadora']['reportes.ver']);
        $this->assertFalse($matriz['contadora']['comandas.crear']);
        $this->assertFalse($matriz['contadora']['cobros.procesar']);
        $this->assertFalse($matriz['contadora']['inventario.gestionar']);
    }

    /**
     * Verifica que los permisos estén agrupados por módulo con sus nombres legibles y descripciones.
     */
    public function test_modulos_y_permisos_contienen_metadatos_legibles(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/matriz-permisos');
        $modulos = $response->json('data.modulos');
        $permisos = $response->json('data.permisos');

        $this->assertNotEmpty($modulos);
        $clavesModulos = array_column($modulos, 'clave');

        $this->assertContains('usuarios', $clavesModulos);
        $this->assertContains('comandas', $clavesModulos);
        $this->assertContains('cobros', $clavesModulos);
        $this->assertContains('inventario', $clavesModulos);
        $this->assertContains('contabilidad', $clavesModulos);
        $this->assertContains('reportes', $clavesModulos);

        foreach ($permisos as $p) {
            $this->assertArrayHasKey('id', $p);
            $this->assertArrayHasKey('name', $p);
            $this->assertArrayHasKey('modulo', $p);
            $this->assertArrayHasKey('accion', $p);
            $this->assertArrayHasKey('descripcion', $p);
            $this->assertNotEmpty($p['descripcion']);
        }
    }
}
