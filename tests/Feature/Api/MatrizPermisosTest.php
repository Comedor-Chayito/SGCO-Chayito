<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de la Matriz de Permisos por Rol RBAC (US-ADM-01 / RF-ADM-008 / CC-85).
 * Valida la consistencia de la matriz de acceso, herencia y restricciones por puesto.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008 (CC-85)
 */
class MatrizPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cajero;
    protected User $mesero;
    protected User $cocinero;
    protected User $contadora;

    /**
     * Configuración inicial del entorno de prueba con la matriz sembrada.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ejecutar sembrado de roles y permisos oficial
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();

        // Crear usuario cocinero de prueba
        $this->cocinero = User::factory()->create([
            'name'   => 'Julia Cocinera',
            'email'  => 'julia.cocina@sgco-chayito.local',
            'activo' => true,
        ]);
        $this->cocinero->assignRole('cocinero');

        // Crear usuario contadora de prueba
        $this->contadora = User::factory()->create([
            'name'   => 'Elena Contadora',
            'email'  => 'elena.conta@sgco-chayito.local',
            'activo' => true,
        ]);
        $this->contadora->assignRole('contadora');
    }

    /**
     * Verifica que el seeder genere todos los roles y permisos oficiales del sistema.
     *
     * @return void
     */
    public function test_seeder_siembra_todos_los_roles_y_permisos_oficiales(): void
    {
        $rolesEsperados = [
            'administrador',
            'cajero',
            'mesero',
            'cocinero',
            'cocinera',
            'contadora',
        ];

        foreach ($rolesEsperados as $rol) {
            $this->assertDatabaseHas('roles', ['name' => $rol, 'guard_name' => 'web']);
        }

        $permisosEsperados = [
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.desactivar',
            'comandas.ver',
            'comandas.crear',
            'cobros.ver',
            'cobros.procesar',
            'inventario.ver',
            'inventario.gestionar',
            'contabilidad.ver',
            'reportes.ver',
        ];

        foreach ($permisosEsperados as $permiso) {
            $this->assertDatabaseHas('permissions', ['name' => $permiso, 'guard_name' => 'web']);
        }
    }

    /**
     * Verifica que el rol de Administrador posea todos los permisos del sistema.
     *
     * @return void
     */
    public function test_rol_administrador_tiene_acceso_a_todos_los_permisos(): void
    {
        $todosLosPermisos = Permission::pluck('name');

        foreach ($todosLosPermisos as $permiso) {
            $this->assertTrue(
                $this->admin->hasPermissionTo($permiso),
                "El administrador debería tener el permiso: {$permiso}"
            );
            $this->assertTrue($this->admin->can($permiso));
        }
    }

    /**
     * Verifica que el rol de Cajero solo tenga permisos de comandas y cobros.
     *
     * @return void
     */
    public function test_rol_cajero_solo_tiene_permisos_de_comandas_y_cobros(): void
    {
        // Permisos concedidos
        $this->assertTrue($this->cajero->hasPermissionTo('comandas.ver'));
        $this->assertTrue($this->cajero->hasPermissionTo('cobros.ver'));
        $this->assertTrue($this->cajero->hasPermissionTo('cobros.procesar'));

        // Permisos denegados
        $this->assertFalse($this->cajero->hasPermissionTo('comandas.crear'));
        $this->assertFalse($this->cajero->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($this->cajero->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($this->cajero->hasPermissionTo('usuarios.editar'));
        $this->assertFalse($this->cajero->hasPermissionTo('usuarios.desactivar'));
        $this->assertFalse($this->cajero->hasPermissionTo('inventario.ver'));
        $this->assertFalse($this->cajero->hasPermissionTo('inventario.gestionar'));
        $this->assertFalse($this->cajero->hasPermissionTo('contabilidad.ver'));
        $this->assertFalse($this->cajero->hasPermissionTo('reportes.ver'));
    }

    /**
     * Verifica que el rol de Mesero solo tenga permisos de visualización y creación de comandas.
     *
     * @return void
     */
    public function test_rol_mesero_solo_tiene_permisos_de_comandas(): void
    {
        // Permisos concedidos
        $this->assertTrue($this->mesero->hasPermissionTo('comandas.ver'));
        $this->assertTrue($this->mesero->hasPermissionTo('comandas.crear'));

        // Permisos denegados
        $this->assertFalse($this->mesero->hasPermissionTo('cobros.ver'));
        $this->assertFalse($this->mesero->hasPermissionTo('cobros.procesar'));
        $this->assertFalse($this->mesero->hasPermissionTo('usuarios.ver'));
        $this->assertFalse($this->mesero->hasPermissionTo('inventario.gestionar'));
        $this->assertFalse($this->mesero->hasPermissionTo('contabilidad.ver'));
        $this->assertFalse($this->mesero->hasPermissionTo('reportes.ver'));
    }

    /**
     * Verifica que el rol de Cocinero solo tenga permisos de visualización de comandas y gestión de inventario.
     *
     * @return void
     */
    public function test_rol_cocinero_solo_tiene_permisos_de_cocina_e_inventario(): void
    {
        // Permisos concedidos
        $this->assertTrue($this->cocinero->hasPermissionTo('comandas.ver'));
        $this->assertTrue($this->cocinero->hasPermissionTo('inventario.ver'));
        $this->assertTrue($this->cocinero->hasPermissionTo('inventario.gestionar'));

        // Permisos denegados
        $this->assertFalse($this->cocinero->hasPermissionTo('comandas.crear'));
        $this->assertFalse($this->cocinero->hasPermissionTo('cobros.procesar'));
        $this->assertFalse($this->cocinero->hasPermissionTo('usuarios.crear'));
        $this->assertFalse($this->cocinero->hasPermissionTo('contabilidad.ver'));
        $this->assertFalse($this->cocinero->hasPermissionTo('reportes.ver'));
    }

    /**
     * Verifica que el rol de Contadora solo tenga permisos de contabilidad y reportes.
     *
     * @return void
     */
    public function test_rol_contadora_solo_tiene_permisos_de_contabilidad_y_reportes(): void
    {
        // Permisos concedidos
        $this->assertTrue($this->contadora->hasPermissionTo('contabilidad.ver'));
        $this->assertTrue($this->contadora->hasPermissionTo('reportes.ver'));

        // Permisos denegados
        $this->assertFalse($this->contadora->hasPermissionTo('comandas.ver'));
        $this->assertFalse($this->contadora->hasPermissionTo('comandas.crear'));
        $this->assertFalse($this->contadora->hasPermissionTo('cobros.procesar'));
        $this->assertFalse($this->contadora->hasPermissionTo('inventario.gestionar'));
        $this->assertFalse($this->contadora->hasPermissionTo('usuarios.crear'));
    }

    /**
     * Verifica la actualización dinámica de permisos al reasignar el rol de un usuario.
     *
     * @return void
     */
    public function test_usuario_actualiza_permisos_al_cambiar_de_rol(): void
    {
        // Inicialmente es mesero
        $this->assertTrue($this->mesero->hasPermissionTo('comandas.crear'));
        $this->assertFalse($this->mesero->hasPermissionTo('cobros.procesar'));

        // Se promueve a cajero
        $this->mesero->syncRoles(['cajero']);
        $this->mesero->refresh();

        // Ahora tiene permisos de cobro y no de toma de comanda
        $this->assertTrue($this->mesero->hasPermissionTo('cobros.procesar'));
        $this->assertFalse($this->mesero->hasPermissionTo('comandas.crear'));
    }

    /**
     * Verifica que el endpoint /api/auth/usuario devuelva el rol y la lista de permisos asignados.
     *
     * @return void
     */
    public function test_endpoint_auth_usuario_retorna_rol_y_matriz_de_permisos(): void
    {
        Sanctum::actingAs($this->mesero);

        $response = $this->getJson('/api/auth/usuario');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuario autenticado obtenido.',
                'data'    => [
                    'id'    => $this->mesero->id,
                    'email' => $this->mesero->email,
                    'rol'   => 'mesero',
                ],
            ]);

        $permisos = $response->json('data.permisos');
        $this->assertIsArray($permisos);
        $this->assertContains('comandas.ver', $permisos);
        $this->assertContains('comandas.crear', $permisos);
        $this->assertNotContains('cobros.procesar', $permisos);
        $this->assertNotContains('usuarios.crear', $permisos);
    }
}

