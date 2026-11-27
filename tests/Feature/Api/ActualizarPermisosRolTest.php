<?php

namespace Tests\Feature\Api;

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Suite de pruebas para el endpoint de actualización de permisos de un rol con auditoría.
 * Valida el cumplimiento del requerimiento funcional RF-ADM-008, historia US-ADM-04 y tarea CC-122.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-18
 * @módulo Administración y Seguridad – US-ADM-04 / RF-ADM-008 / CC-122
 */
class ActualizarPermisosRolTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    private User $cajero;

    private User $mesero;

    private User $cocinero;

    private User $contadora;

    /**
     * Prepara el entorno con roles oficiales, usuarios y permisos iniciales de prueba.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-11-18
     * @módulo Administración y Seguridad – US-ADM-04 / CC-122
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin Matriz']);
        $this->admin->assignRole('administrador');

        $this->socio = User::factory()->create(['name' => 'Socio Auditor']);
        $this->socio->assignRole('socio');

        $this->cajero = User::factory()->create(['name' => 'Cajero Test']);
        $this->cajero->assignRole('cajero');

        $this->mesero = User::factory()->create(['name' => 'Mesero Test']);
        $this->mesero->assignRole('mesero');

        $this->cocinero = User::factory()->create(['name' => 'Cocinero Test']);
        $this->cocinero->assignRole('cocinero');

        $this->contadora = User::factory()->create(['name' => 'Contadora Test']);
        $this->contadora->assignRole('contadora');

        Sanctum::actingAs($this->admin);
    }

    // =========================================================================
    // SECCIÓN 1: Autenticación y Autorización RBAC (CC-122)
    // =========================================================================

    /**
     * 1. Petición sin token de autenticación recibe 401 Unauthorized.
     */
    public function test_sin_autenticacion_recibe_401(): void
    {
        $this->app['auth']->forgetGuards();

        $respuesta = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver'],
        ]);

        $respuesta->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'No autenticado. Token ausente o inválido.');
    }

    /**
     * 2. Roles no administradores (socio, cajero, mesero, cocinero, contadora) reciben 403 Forbidden.
     */
    public function test_roles_no_administradores_reciben_403_al_intentar_modificar_permisos(): void
    {
        $rolesNoAutorizados = [
            $this->socio,
            $this->cajero,
            $this->mesero,
            $this->cocinero,
            $this->contadora,
        ];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);

            $respuesta = $this->putJson('/api/roles/cajero/permisos', [
                'permisos' => ['cobros.ver'],
            ]);

            $respuesta->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'No tienes permisos suficientes para realizar esta acción.');
        }
    }

    // =========================================================================
    // SECCIÓN 2: Actualización de Permisos y Trazabilidad (CC-122)
    // =========================================================================

    /**
     * 3. Administrador actualiza los permisos de un rol por su nombre con código 200.
     */
    public function test_administrador_actualiza_permisos_por_nombre_de_rol(): void
    {
        $nuevosPermisos = ['cobros.ver', 'cobros.procesar', 'comandas.ver', 'comandas.crear'];

        $respuesta = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => $nuevosPermisos,
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('message', "Permisos del rol 'cajero' actualizados correctamente.")
            ->assertJsonPath('data.name', 'cajero')
            ->assertJsonPath('data.total_permisos', 4);

        $rolCajero = Role::findByName('cajero', 'web');
        $this->assertCount(4, $rolCajero->permissions);
        foreach ($nuevosPermisos as $permiso) {
            $this->assertTrue($rolCajero->hasPermissionTo($permiso));
        }
    }

    /**
     * 4. Administrador actualiza los permisos de un rol identificándolo por su ID numérico.
     */
    public function test_administrador_actualiza_permisos_por_id_de_rol(): void
    {
        $rolMesero = Role::findByName('mesero', 'web');

        $respuesta = $this->putJson("/api/roles/{$rolMesero->id}/permisos", [
            'permisos' => ['comandas.ver', 'comandas.crear', 'inventario.ver'],
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.id', $rolMesero->id)
            ->assertJsonPath('data.name', 'mesero')
            ->assertJsonPath('data.total_permisos', 3);

        $this->assertTrue($rolMesero->fresh()->hasPermissionTo('inventario.ver'));
    }

    /**
     * 5. Endpoint alternativo /api/matriz-permisos/{rol} actualiza correctamente los permisos.
     */
    public function test_ruta_alternativa_matriz_permisos_actualiza_correctamente(): void
    {
        $respuesta = $this->putJson('/api/matriz-permisos/cocinero', [
            'permisos' => ['inventario.ver', 'inventario.gestionar'],
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.name', 'cocinero');

        $rolCocinero = Role::findByName('cocinero', 'web');
        $this->assertTrue($rolCocinero->hasPermissionTo('inventario.gestionar'));
    }

    /**
     * 6. La actualización registra un evento en la bitácora de auditoría con datos anteriores, nuevos, agregados y removidos.
     */
    public function test_actualizacion_registra_auditoria_detallada_con_responsable(): void
    {
        $rolContadora = Role::findByName('contadora', 'web');
        $permisosIniciales = $rolContadora->permissions->pluck('name')->toArray();

        // Modificar permisos de contadora (retirar contabilidad.ver y agregar inventario.ver, conservando reportes.ver)
        $respuesta = $this->putJson('/api/roles/contadora/permisos', [
            'permisos' => ['reportes.ver', 'inventario.ver'],
        ]);

        $respuesta->assertStatus(200);

        $this->assertDatabaseHas('auditorias', [
            'usuario_id'   => $this->admin->id,
            'accion'       => 'permisos_rol_actualizados',
            'entidad_type' => $rolContadora->getMorphClass(),
            'entidad_id'   => $rolContadora->id,
        ]);

        $auditoria = Auditoria::where('accion', 'permisos_rol_actualizados')
            ->where('entidad_id', $rolContadora->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditoria);
        $this->assertSame($this->admin->id, $auditoria->usuario_id);
        $this->assertSame('contadora', $auditoria->datos_nuevos['rol']);
        $this->assertContains('inventario.ver', $auditoria->datos_nuevos['permisos']);
        $this->assertContains('inventario.ver', $auditoria->datos_nuevos['agregados']);
        $this->assertContains('contabilidad.ver', $auditoria->datos_nuevos['removidos']);
    }

    /**
     * 7. Los usuarios asignados a ese rol adquieren o pierden acceso inmediatamente tras la sincronización.
     */
    public function test_cambio_de_permisos_se_refleja_inmediatamente_en_los_usuarios_del_rol(): void
    {
        // Cajero inicialmente no puede crear comandas
        $this->assertFalse($this->cajero->hasPermissionTo('comandas.crear'));

        // Administrador otorga comandas.crear a cajeros
        $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver', 'cobros.procesar', 'comandas.ver', 'comandas.crear'],
        ])->assertStatus(200);

        // Limpiar caché de permisos en el modelo y verificar reflejo inmediato
        $this->cajero->unsetRelation('permissions');
        $this->cajero->unsetRelation('roles');
        $this->assertTrue($this->cajero->fresh()->hasPermissionTo('comandas.crear'));
    }

    /**
     * 8. Permite revocar todos los permisos a un rol operativo asignando un arreglo vacío.
     */
    public function test_rol_operativo_puede_quedar_sin_permisos_con_arreglo_vacio(): void
    {
        $respuesta = $this->putJson('/api/roles/mesero/permisos', [
            'permisos' => [],
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.total_permisos', 0)
            ->assertJsonPath('data.permisos', []);

        $rolMesero = Role::findByName('mesero', 'web');
        $this->assertCount(0, $rolMesero->permissions);
    }

    /**
     * 9. Duplicados en el arreglo de permisos se normalizan sin generar error de sincronización.
     */
    public function test_duplicados_en_lista_de_permisos_se_sincronizan_correctamente(): void
    {
        $respuesta = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver', 'cobros.ver', 'cobros.procesar'],
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('data.total_permisos', 2);
    }

    // =========================================================================
    // SECCIÓN 3: Validaciones y Reglas de Negocio (CC-122)
    // =========================================================================

    /**
     * 10. Rechaza la petición si el rol especificado no existe en el sistema (código 404).
     */
    public function test_rechaza_rol_inexistente_con_404(): void
    {
        $respuesta = $this->putJson('/api/roles/rol_invalido_inexistente/permisos', [
            'permisos' => ['comandas.ver'],
        ]);

        $respuesta->assertStatus(404)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', "El rol especificado 'rol_invalido_inexistente' no existe en el sistema.");
    }

    /**
     * 11. Rechaza la petición sin la clave permisos o con tipo no array (código 422).
     */
    public function test_rechaza_peticion_sin_campo_permisos_con_422(): void
    {
        $respVacia = $this->putJson('/api/roles/cajero/permisos', []);
        $respVacia->assertStatus(422)
            ->assertJsonValidationErrors(['permisos']);

        $respNoArray = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => 'cobros.ver',
        ]);
        $respNoArray->assertStatus(422)
            ->assertJsonValidationErrors(['permisos']);
    }

    /**
     * 12. Rechaza la petición si uno o más permisos no existen en el catálogo oficial (código 422).
     */
    public function test_rechaza_permiso_inexistente_en_catalogo_con_422(): void
    {
        $respuesta = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver', 'permiso_super_invalido'],
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['permisos.1']);
    }

    /**
     * 13. Auto-protección del administrador: rechaza revocar los permisos críticos usuarios.ver o usuarios.editar (422).
     */
    public function test_administrador_no_puede_revocar_permisos_criticos_usuarios_ver_o_editar(): void
    {
        $respuesta = $this->putJson('/api/roles/administrador/permisos', [
            'permisos' => ['comandas.ver', 'cobros.ver'], // Omite usuarios.ver y usuarios.editar
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['permisos']);

        $rolAdmin = Role::findByName('administrador', 'web');
        $this->assertTrue($rolAdmin->hasPermissionTo('usuarios.ver'));
        $this->assertTrue($rolAdmin->hasPermissionTo('usuarios.editar'));
    }
}
