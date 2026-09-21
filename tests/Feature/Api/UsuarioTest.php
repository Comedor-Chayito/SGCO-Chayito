<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas del CRUD de usuarios y roles RBAC (US-ADM-01 / RF-ADM-008 / CC-84 / CC-88).
 * Verifica la gestión completa de usuarios, roles, seguridad de tokens y restricciones.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Administración y Seguridad – RF-ADM-008 (CC-88)
 */
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $mesero;

    /**
     * Configuración inicial antes de cada prueba.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ejecutar sembrado de roles y permisos
        $this->seed(RolePermissionSeeder::class);

        // Obtener usuario administrador creado por el seeder
        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->admin->syncRoles(['administrador']);

        // Obtener usuario mesero (rol no administrador)
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();
        $this->mesero->syncRoles(['mesero']);
    }

    /**
     * Verifica que un administrador autenticado pueda listar los usuarios del sistema.
     *
     * @return void
     */
    public function test_administrador_puede_listar_usuarios(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuarios obtenidos correctamente.',
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'email', 'activo', 'rol', 'roles'],
                ],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Verifica que un administrador pueda registrar un nuevo usuario con rol asignado.
     *
     * @return void
     */
    public function test_administrador_puede_crear_usuario_con_rol(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name'     => 'Ana Contadora',
            'email'    => 'ana.contadora@sgco-chayito.local',
            'password' => 'PasswordSeguro123!',
            'rol'      => 'contadora',
            'activo'   => true,
        ];

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuario registrado exitosamente.',
                'data'    => [
                    'name'   => 'Ana Contadora',
                    'email'  => 'ana.contadora@sgco-chayito.local',
                    'activo' => true,
                    'rol'    => 'contadora',
                ],
            ]);

        // Verificar persistencia en base de datos
        $this->assertDatabaseHas('users', [
            'email'  => 'ana.contadora@sgco-chayito.local',
            'activo' => true,
        ]);

        $nuevoUsuario = User::where('email', 'ana.contadora@sgco-chayito.local')->first();
        $this->assertTrue($nuevoUsuario->hasRole('contadora'));
        $this->assertTrue(Hash::check('PasswordSeguro123!', $nuevoUsuario->password));
    }

    /**
     * Verifica validación de campos obligatorios al crear usuario.
     *
     * @return void
     */
    public function test_crear_usuario_valida_campos_requeridos(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/usuarios', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'rol']);
    }

    /**
     * Verifica que no se permita registrar un usuario con correo duplicado.
     *
     * @return void
     */
    public function test_crear_usuario_rechaza_correo_duplicado(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name'     => 'Otro Usuario',
            'email'    => $this->mesero->email, // Correo existente
            'password' => 'PasswordSeguro123!',
            'rol'      => 'cajero',
        ];

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Verifica que no se permita asignar un rol que no exista en el sistema.
     *
     * @return void
     */
    public function test_crear_usuario_rechaza_rol_invalido(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name'     => 'Rol Falso',
            'email'    => 'falso@sgco-chayito.local',
            'password' => 'Password123!',
            'rol'      => 'superman_rol',
        ];

        $response = $this->postJson('/api/usuarios', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rol']);
    }

    /**
     * Verifica consulta del detalle de un usuario específico.
     *
     * @return void
     */
    public function test_administrador_puede_ver_detalle_de_usuario(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/usuarios/{$this->mesero->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Detalle del usuario obtenido.',
                'data'    => [
                    'id'    => $this->mesero->id,
                    'name'  => $this->mesero->name,
                    'email' => $this->mesero->email,
                    'rol'   => 'mesero',
                ],
            ]);
    }

    /**
     * Verifica que un administrador pueda modificar los datos y el rol de un usuario.
     *
     * @return void
     */
    public function test_administrador_puede_actualizar_usuario_y_cambiar_rol(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name'  => 'Carlos Cajero Promovido',
            'email' => 'carlos.cajero@sgco-chayito.local',
            'rol'   => 'cajero',
        ];

        $response = $this->putJson("/api/usuarios/{$this->mesero->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuario actualizado correctamente.',
                'data'    => [
                    'name'  => 'Carlos Cajero Promovido',
                    'email' => 'carlos.cajero@sgco-chayito.local',
                    'rol'   => 'cajero',
                ],
            ]);

        $this->mesero->refresh();
        $this->assertSame('Carlos Cajero Promovido', $this->mesero->name);
        $this->assertTrue($this->mesero->hasRole('cajero'));
        $this->assertFalse($this->mesero->hasRole('mesero'));
    }

    /**
     * Verifica que un administrador pueda desactivar una cuenta y que sus tokens queden revocados.
     *
     * @return void
     */
    public function test_administrador_puede_desactivar_usuario_y_revoca_sus_tokens(): void
    {
        // 1. Crear un token previo para el mesero
        $tokenMesero = $this->mesero->createToken('token-mesero')->plainTextToken;
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->mesero->id,
        ]);

        // 2. Administrador desactiva la cuenta del mesero
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/usuarios/{$this->mesero->id}/toggle-activo");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Cuenta de usuario desactivada correctamente.',
                'data'    => [
                    'id'     => $this->mesero->id,
                    'activo' => false,
                ],
            ]);

        // 3. Verificar que activo sea false en la base de datos
        $this->assertDatabaseHas('users', [
            'id'     => $this->mesero->id,
            'activo' => false,
        ]);

        // 4. Verificar que todos sus tokens hayan sido eliminados de personal_access_tokens
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->mesero->id,
        ]);
    }

    /**
     * Verifica que una cuenta de usuario desactivada no pueda iniciar sesión.
     *
     * @return void
     */
    public function test_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $this->mesero->update(['activo' => false]);

        $response = $this->postJson('/api/auth/login', [
            'correo'     => $this->mesero->email,
            'contrasena' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment([
                'email' => ['Esta cuenta de usuario se encuentra desactivada.'],
            ]);
    }

    /**
     * Verifica que un administrador pueda reactivar una cuenta previamente desactivada.
     *
     * @return void
     */
    public function test_administrador_puede_reactivar_usuario(): void
    {
        $this->mesero->update(['activo' => false]);

        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/usuarios/{$this->mesero->id}/toggle-activo");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Cuenta de usuario reactivada correctamente.',
                'data'    => [
                    'id'     => $this->mesero->id,
                    'activo' => true,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id'     => $this->mesero->id,
            'activo' => true,
        ]);
    }

    /**
     * Verifica regla de seguridad: el único administrador activo no puede desactivar su propia cuenta.
     *
     * @return void
     */
    public function test_administrador_no_puede_desactivarse_a_si_mismo_si_es_el_unico(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->patchJson("/api/usuarios/{$this->admin->id}/toggle-activo");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usuario']);
    }

    /**
     * Verifica que un administrador pueda eliminar permanentemente un usuario sin registros asociados.
     *
     * @return void
     */
    public function test_administrador_puede_eliminar_usuario_sin_registros_asociados(): void
    {
        Sanctum::actingAs($this->admin);

        $usuarioAEliminar = User::factory()->create([
            'name'   => 'Usuario Descartable',
            'email'  => 'descartable@sgco-chayito.local',
            'activo' => true,
        ]);
        $usuarioAEliminar->assignRole('mesero');

        $response = $this->deleteJson("/api/usuarios/{$usuarioAEliminar->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuario eliminado correctamente.',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $usuarioAEliminar->id,
        ]);
    }

    /**
     * Verifica que un administrador no pueda eliminar su propia cuenta en sesión.
     *
     * @return void
     */
    public function test_administrador_no_puede_eliminar_su_propia_cuenta(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->deleteJson("/api/usuarios/{$this->admin->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usuario']);
    }

    /**
     * Verifica que un usuario con comandas asociadas no pueda ser eliminado (integridad referencial).
     *
     * @return void
     */
    public function test_administrador_no_puede_eliminar_usuario_con_comandas_asociadas(): void
    {
        Sanctum::actingAs($this->admin);

        // Crear una comanda asociada al usuario mesero
        \App\Models\Comanda::create([
            'usuario_id' => $this->mesero->id,
            'mesa'       => 'Mesa 1',
            'canal'      => 'mesa',
            'estado'     => 'pendiente',
            'subtotal'   => 15.00,
            'total'      => 15.00,
        ]);

        $response = $this->deleteJson("/api/usuarios/{$this->mesero->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['usuario']);

        $this->assertDatabaseHas('users', [
            'id' => $this->mesero->id,
        ]);
    }

    /**
     * Verifica que peticiones no autenticadas sean rechazadas con 401.
     *
     * @return void
     */
    public function test_peticion_sin_autenticacion_al_crud_devuelve_401(): void
    {
        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(401);
    }

    /**
     * Verifica que usuarios con roles no autorizados (ej. mesero) reciban 403 Forbidden.
     *
     * @return void
     */
    public function test_usuario_sin_rol_administrador_recibe_403(): void
    {
        Sanctum::actingAs($this->mesero);

        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(403);
    }

    /**
     * Verifica que el endpoint de roles devuelva la lista de roles oficiales disponibles.
     *
     * @return void
     */
    public function test_listar_roles_devuelve_catalogo_oficial(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Roles disponibles obtenidos correctamente.',
            ]);

        $roles = $response->json('data');
        $this->assertContains('administrador', $roles);
        $this->assertContains('cajero', $roles);
        $this->assertContains('mesero', $roles);
        $this->assertContains('cocinero', $roles);
        $this->assertContains('contadora', $roles);
    }
}

