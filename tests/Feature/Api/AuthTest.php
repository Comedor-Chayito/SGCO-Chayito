<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas de autenticación segura y cifrado de credenciales (US-ADM-02 / Sec. 3.5.1 / CC-93).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo Administración y Seguridad – Sec. 3.5.1
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $usuario;
    protected string $passwordPlano;

    /**
     * Configuración inicial antes de cada prueba de autenticación.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->passwordPlano = 'PasswordSeguro123!';

        $this->usuario = User::factory()->create([
            'name'     => 'Administrador General',
            'email'    => 'admin@sgco-chayito.local',
            'password' => Hash::make($this->passwordPlano),
        ]);
    }

    /**
     * Verifica que la contraseña del usuario esté cifrada utilizando el algoritmo Bcrypt.
     * Criterio de aceptación: Contraseña cifrada con Bcrypt.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_contrasena_esta_cifrada_con_driver_bcrypt(): void
    {
        // 1. Verificar configuración de hashing
        $this->assertSame('bcrypt', config('hashing.driver'));

        // 2. Verificar información del hash generado
        $hashInfo = Hash::info($this->usuario->password);
        $this->assertSame('bcrypt', $hashInfo['algoName']);

        // 3. Verificar prefijo estándar de Bcrypt ($2y$)
        $this->assertStringStartsWith('$2y$', $this->usuario->password);

        // 4. Verificar que Hash::check valide la contraseña en texto plano
        $this->assertTrue(Hash::check($this->passwordPlano, $this->usuario->password));
        $this->assertFalse(Hash::check('ContrasenaErronea', $this->usuario->password));
    }

    /**
     * Verifica inicio de sesión exitoso por correo corporativo con campos estándar (email/password).
     * Criterios: Login por correo corporativo y Token de sesión seguro.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_usuario_puede_iniciar_sesion_con_credenciales_validas(): void
    {
        $payload = [
            'email'    => 'admin@sgco-chayito.local',
            'password' => $this->passwordPlano,
        ];

        $response = $this->postJson('/api/sesiones', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'usuario' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Sesión iniciada correctamente.',
                'data'    => [
                    'usuario' => [
                        'id'    => $this->usuario->id,
                        'email' => 'admin@sgco-chayito.local',
                    ],
                ],
            ]);

        // Verificar que el token retornado sea un string no vacío
        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verificar que el token existe en la base de datos (tabla personal_access_tokens)
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id'   => $this->usuario->id,
            'name'           => 'token-sesion',
        ]);
    }

    /**
     * Verifica inicio de sesión exitoso con alias de campos en español (correo/contrasena).
     * Soporte para el frontend FSD SGCO-Chayito.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_usuario_puede_iniciar_sesion_con_campos_en_espanol_y_ruta_auth_login(): void
    {
        $payload = [
            'correo'     => 'admin@sgco-chayito.local',
            'contrasena' => $this->passwordPlano,
        ];

        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Sesión iniciada correctamente.',
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
    }

    /**
     * Verifica que el inicio de sesión falle cuando la contraseña es incorrecta.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_login_rechaza_contrasena_incorrecta(): void
    {
        $payload = [
            'email'    => 'admin@sgco-chayito.local',
            'password' => 'ClaveTotalmenteErronea!',
        ];

        $response = $this->postJson('/api/sesiones', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Verifica que el inicio de sesión falle con mensaje genérico si el correo no existe.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_login_rechaza_correo_inexistente(): void
    {
        $payload = [
            'email'    => 'noexiste@sgco-chayito.local',
            'password' => 'CualquierClave123!',
        ];

        $response = $this->postJson('/api/sesiones', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Verifica validación de formato de correo corporativo no válido.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_login_rechaza_formato_de_correo_invalido(): void
    {
        $payload = [
            'email'    => 'correo-no-valido',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/sesiones', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment([
                'email' => ['El correo no tiene un formato válido.'],
            ]);
    }

    /**
     * Verifica validación cuando los campos requeridos están ausentes.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_login_rechaza_peticion_sin_credenciales(): void
    {
        $response = $this->postJson('/api/sesiones', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonFragment([
                'email'    => ['El correo es obligatorio.'],
                'password' => ['La contraseña es obligatoria.'],
            ]);
    }

    /**
     * Verifica que el token Sanctum emitido permita acceder a rutas protegidas.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_token_sanctum_permite_acceder_a_rutas_protegidas(): void
    {
        // 1. Iniciar sesión y obtener token
        $loginResponse = $this->postJson('/api/sesiones', [
            'email'    => 'admin@sgco-chayito.local',
            'password' => $this->passwordPlano,
        ]);

        $token = $loginResponse->json('data.token');

        // 2. Consultar ruta protegida con Bearer Token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/usuario');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Usuario autenticado obtenido.',
                'data'    => [
                    'id'    => $this->usuario->id,
                    'email' => 'admin@sgco-chayito.local',
                ],
            ]);
    }

    /**
     * Verifica que el acceso a rutas protegidas sin token devuelva 401 Unauthorized.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_peticion_sin_token_a_ruta_protegida_devuelve_401(): void
    {
        $response = $this->getJson('/api/auth/usuario');

        $response->assertStatus(401);
    }

    /**
     * Verifica que el cierre de sesión revoque el token de acceso y bloquee accesos subsiguientes.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_usuario_puede_cerrar_sesion_y_token_es_revocado(): void
    {
        // 1. Iniciar sesión
        $loginResponse = $this->postJson('/api/sesiones', [
            'email'    => 'admin@sgco-chayito.local',
            'password' => $this->passwordPlano,
        ]);

        $token = $loginResponse->json('data.token');

        // 2. Cerrar sesión vía DELETE /api/sesiones
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/sesiones');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Sesión cerrada correctamente.',
                'data'    => null,
            ]);

        // Verificar que el token se eliminó de la base de datos
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->usuario->id,
        ]);

        // En pruebas de Laravel, refrescar los guards para que la nueva petición
        // revalide las cabeceras contra la base de datos sin usar la instancia en memoria
        app('auth')->forgetGuards();

        // 3. Verificar que el token ya no permita acceder a rutas protegidas
        $intentoProtegido = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/usuario');

        $intentoProtegido->assertStatus(401);
    }

    /**
     * Verifica cierre de sesión con alias de ruta POST /api/auth/logout.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo Administración y Seguridad – Sec. 3.5.1
     *
     * @return void
     */
    public function test_usuario_puede_cerrar_sesion_con_ruta_auth_logout(): void
    {
        $loginResponse = $this->postJson('/api/auth/login', [
            'correo'     => 'admin@sgco-chayito.local',
            'contrasena' => $this->passwordPlano,
        ]);

        $token = $loginResponse->json('data.token');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'ok',
                'message' => 'Sesión cerrada correctamente.',
            ]);

        // Verificar que el token se eliminó de la base de datos
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->usuario->id,
        ]);

        app('auth')->forgetGuards();

        $intentoProtegido = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/usuario');

        $intentoProtegido->assertStatus(401);
    }
}

