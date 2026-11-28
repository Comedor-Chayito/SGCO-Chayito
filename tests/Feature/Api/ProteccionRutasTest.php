<?php

namespace Tests\Feature\Api;

use App\Models\Comanda;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\Platillo;
use App\Models\Receta;
use App\Models\Requisicion;
use App\Models\User;
use Database\Seeders\InventarioSeeder;
use Database\Seeders\PosSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SubcategoriaEgresoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de Protección de Rutas de la API (Módulo A1 / US-ADM-02 / RF-ADM-008 / CC-102).
 *
 * Certifica que todos los endpoints protegidos respondan:
 * - HTTP 401 Unauthorized cuando la petición no incluye token de Sanctum o es inválido.
 * - HTTP 403 Forbidden cuando el token es válido pero el usuario no posee el rol requerido.
 * - HTTP 200/201/OK cuando el usuario cuenta con el rol autorizado según la matriz de acceso.
 *
 * @autor  Equipo SGCO-Chayito
 *
 * @fecha  2026-11-17
 *
 * @módulo Seguridad y Administración — A1 (CC-102)
 */
class ProteccionRutasTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $socio;

    protected User $cajero;

    protected User $mesero;

    protected User $cocinero;

    protected User $contadora;

    protected Platillo $platillo;

    protected Mesa $mesa;

    protected Comanda $comanda;

    protected Insumo $insumo;

    protected Receta $receta;

    protected Requisicion $requisicion;

    /**
     * Configuración inicial del entorno de prueba con roles y catálogos base.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PosSeeder::class);
        $this->seed(InventarioSeeder::class);
        $this->seed(SubcategoriaEgresoSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->socio = User::where('email', 'socio@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();
        $this->cocinero = User::where('email', 'cocinero@sgco-chayito.local')->first();
        $this->contadora = User::where('email', 'contadora@sgco-chayito.local')->first();

        $this->platillo = Platillo::first();
        $this->mesa = Mesa::first();
        $this->insumo = Insumo::first();
        $this->receta = Receta::first();

        $this->comanda = Comanda::create([
            'mesa_id' => $this->mesa->id,
            'usuario_id' => $this->mesero->id,
            'canal' => 'mesa',
            'estado' => 'pendiente',
            'subtotal' => 5.00,
            'observaciones' => 'Sin cebolla',
        ]);

        $this->requisicion = Requisicion::create([
            'usuario_solicita_id' => $this->cocinero->id,
            'area_solicitante' => 'cocina',
            'estado' => 'pendiente',
        ]);
    }

    /**
     * 1. Rutas públicas no exigen token ni responden 401.
     */
    public function test_rutas_publicas_de_autenticacion_no_exigen_token(): void
    {
        // Sesiones estándar
        $respSesiones = $this->postJson('/api/sesiones', [
            'email' => 'admin@sgco-chayito.local',
            'password' => 'Admin1234!',
        ]);
        $this->assertNotEquals(401, $respSesiones->status());

        // Alias frontend
        $respLogin = $this->postJson('/api/auth/login', [
            'correo' => 'admin@sgco-chayito.local',
            'contrasena' => 'Admin1234!',
        ]);
        $this->assertNotEquals(401, $respLogin->status());
    }

    /**
     * 2. Rutas base protegidas responden 401 sin token y 200 con cualquier rol autenticado.
     */
    public function test_rutas_base_protegidas_rechazan_sin_token_y_permiten_con_token(): void
    {
        // Sin token -> 401
        $this->getJson('/api/auth/usuario')->assertStatus(401);
        $this->postJson('/api/auth/logout')->assertStatus(401);
        $this->deleteJson('/api/sesiones')->assertStatus(401);

        // Con token de cualquier rol (ej. mesero) -> 200
        Sanctum::actingAs($this->mesero);
        $this->getJson('/api/auth/usuario')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $this->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }

    /**
     * 3. Módulo de Administración de Usuarios (role:administrador|socio).
     */
    public function test_modulo_usuarios_y_roles_protege_401_y_403(): void
    {
        $rutas = [
            ['GET',    '/api/roles'],
            ['GET',    '/api/usuarios'],
            ['POST',   '/api/usuarios'],
            ['GET',    "/api/usuarios/{$this->cajero->id}"],
            ['PUT',    "/api/usuarios/{$this->cajero->id}"],
            ['PATCH',  "/api/usuarios/{$this->cajero->id}/toggle-activo"],
            ['DELETE', "/api/usuarios/{$this->cajero->id}"],
        ];

        // A. Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $response = $this->json($metodo, $url);
            $response->assertStatus(401);
            $response->assertJson([
                'status' => 'error',
                'message' => 'No autenticado. Token ausente o inválido.',
                'data' => null,
            ]);
        }

        // B. Roles operativos no autorizados (mesero, cajero, cocinero, contadora) -> 403
        $rolesNoAutorizados = [$this->mesero, $this->cajero, $this->cocinero, $this->contadora];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $response = $this->json($metodo, $url);
                $this->assertEquals(403, $response->status(), "Ruta {$metodo} {$url} no devolvió 403 para {$usuario->email}");
                $response->assertJson([
                    'status' => 'error',
                    'message' => 'No tienes permisos suficientes para realizar esta acción.',
                    'data' => null,
                ]);
            }
        }

        // C. Administrador y Socio autorizados -> no responden 401 ni 403
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/roles')->assertStatus(200);
        $this->getJson('/api/usuarios')->assertStatus(200);

        Sanctum::actingAs($this->socio);
        $this->getJson('/api/roles')->assertStatus(200);
        $this->getJson('/api/usuarios')->assertStatus(200);
    }

    /**
     * 4. Módulo POS - Cobros, Ventas, Retiros y Cortes de Caja (role:cajero|administrador|socio).
     */
    public function test_modulo_pos_cobros_retiros_y_caja_protege_401_y_403(): void
    {
        $rutas = [
            ['POST', '/api/cobros'],
            ['POST', '/api/ventas'],
            ['GET',  '/api/cobros/1'],
            ['GET',  '/api/ventas/1'],
            ['GET',  '/api/retiros'],
            ['POST', '/api/retiros'],
            ['GET',  '/api/aperturas-caja/turno'],
            ['POST', '/api/aperturas-caja'],
            ['POST', '/api/cierres-caja'],
            ['GET',  '/api/cierres-caja'],
            ['GET',  '/api/cierres-caja/2026-10-20'],
        ];

        // A. Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // B. Roles no autorizados (mesero, cocinero, contadora) -> 403
        $rolesNoAutorizados = [$this->mesero, $this->cocinero, $this->contadora];

        foreach ($rolesNoAutorizados as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $response = $this->json($metodo, $url);
                $this->assertEquals(403, $response->status(), "Ruta {$metodo} {$url} debió ser 403 para {$usuario->email}");
            }
        }

        // C. Cajero, Administrador y Socio autorizados -> no devuelven 401 ni 403
        foreach ([$this->cajero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->assertNotEquals(403, $this->getJson('/api/retiros')->status());
            $this->assertNotEquals(403, $this->getJson('/api/cierres-caja')->status());
        }
    }

    /**
     * 5. Módulo Contabilidad - Egresos y Compras IVA (role:contadora|administrador|socio).
     */
    public function test_modulo_contabilidad_egresos_y_compras_protege_401_y_403(): void
    {
        $rutas = [
            ['GET',  '/api/subcategorias-egreso'],
            ['GET',  '/api/egresos'],
            ['POST', '/api/egresos'],
            ['GET',  '/api/compras'],
            ['POST', '/api/compras'],
        ];

        // A. Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // B. Roles operativos no autorizados (mesero, cajero, cocinero) -> 403
        foreach ([$this->mesero, $this->cajero, $this->cocinero] as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $response = $this->json($metodo, $url);
                $this->assertEquals(403, $response->status(), "Ruta {$metodo} {$url} debió dar 403 para {$usuario->email}");
            }
        }

        // C. Contadora, Administrador y Socio autorizados -> 200 en lecturas
        foreach ([$this->contadora, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/subcategorias-egreso')->assertStatus(200);
            $this->getJson('/api/egresos')->assertStatus(200);
            $this->getJson('/api/compras')->assertStatus(200);
        }
    }

    /**
     * 6. Módulo POS - Platillos (role:mesero|cajero|cocinero|cocinera|administrador|socio).
     */
    public function test_modulo_pos_platillos_protege_401_y_403(): void
    {
        // Sin token -> 401
        $this->getJson('/api/platillos')->assertStatus(401);

        // Contadora no tiene acceso a platillos -> 403
        Sanctum::actingAs($this->contadora);
        $this->getJson('/api/platillos')->assertStatus(403);

        // Mesero, Cajero, Cocinero, Administrador y Socio -> 200
        foreach ([$this->mesero, $this->cajero, $this->cocinero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/platillos')->assertStatus(200);
        }
    }

    /**
     * 7. Módulo POS - Mesas y Toma de Comandas (role:mesero|cajero|administrador|socio).
     */
    public function test_modulo_pos_mesas_y_comandas_protege_401_y_403(): void
    {
        $rutas = [
            ['GET',  '/api/mesas'],
            ['POST', '/api/comandas'],
        ];

        // Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // Cocinero y Contadora no autorizados -> 403
        foreach ([$this->cocinero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $this->assertEquals(403, $this->json($metodo, $url)->status());
            }
        }

        // Mesero, Cajero, Admin y Socio autorizados
        foreach ([$this->mesero, $this->cajero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/mesas')->assertStatus(200);
        }
    }

    /**
     * 8. Módulo POS - Listado General de Comandas (role:cajero|administrador|socio).
     */
    public function test_modulo_pos_listado_general_comandas_protege_401_y_403(): void
    {
        // Sin token -> 401
        $this->getJson('/api/comandas')->assertStatus(401);

        // Mesero, Cocinero y Contadora -> 403
        foreach ([$this->mesero, $this->cocinero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/comandas')->assertStatus(403);
        }

        // Cajero, Admin y Socio -> 200
        foreach ([$this->cajero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/comandas')->assertStatus(200);
        }
    }

    /**
     * 9. Módulo Cocina - Cola de Cocina e Impresión (role:cocinero|cocinera|administrador|socio).
     */
    public function test_modulo_cocina_cola_e_impresion_protege_401_y_403(): void
    {
        $rutas = [
            ['GET', '/api/comandas/cocina'],
            ['GET', "/api/comandas/{$this->comanda->id}/impresion"],
        ];

        // Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // Mesero, Cajero y Contadora -> 403
        foreach ([$this->mesero, $this->cajero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $this->assertEquals(403, $this->json($metodo, $url)->status());
            }
        }

        // Cocinero, Administrador y Socio -> 200
        foreach ([$this->cocinero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/comandas/cocina')->assertStatus(200);
            $this->getJson("/api/comandas/{$this->comanda->id}/impresion")->assertStatus(200);
        }
    }

    /**
     * 10. Módulo Comandas - Cambio de Estado (role:cocinero|cocinera|cajero|administrador|socio).
     */
    public function test_modulo_comandas_cambio_estado_protege_401_y_403(): void
    {
        $url = "/api/comandas/{$this->comanda->id}/estado";

        // Sin token -> 401
        $this->patchJson($url, ['estado' => 'en_preparacion'])->assertStatus(401);

        // Mesero y Contadora -> 403
        foreach ([$this->mesero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->patchJson($url, ['estado' => 'en_preparacion'])->assertStatus(403);
        }

        // Cocinero, Cajero, Admin y Socio tienen autorización
        Sanctum::actingAs($this->cocinero);
        $this->assertNotEquals(403, $this->patchJson($url, ['estado' => 'en_preparacion'])->status());
    }

    /**
     * 11. Módulo Inventario - Insumos y Recetas (role:cocinero|cocinera|administrador|socio).
     */
    public function test_modulo_inventario_insumos_y_recetas_protege_401_y_403(): void
    {
        $rutas = [
            ['GET',    '/api/insumos'],
            ['POST',   '/api/insumos'],
            ['GET',    '/api/insumos/alertas'],
            ['GET',    "/api/insumos/{$this->insumo->id}"],
            ['PUT',    "/api/insumos/{$this->insumo->id}"],
            ['DELETE', "/api/insumos/{$this->insumo->id}"],
            ['GET',    "/api/platillos/{$this->platillo->id}/recetas"],
            ['POST',   "/api/platillos/{$this->platillo->id}/recetas"],
            ['PUT',    "/api/platillos/{$this->platillo->id}/recetas/{$this->receta->id}"],
            ['DELETE', "/api/platillos/{$this->platillo->id}/recetas/{$this->receta->id}"],
        ];

        // Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // Mesero, Cajero y Contadora -> 403
        foreach ([$this->mesero, $this->cajero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $this->assertEquals(403, $this->json($metodo, $url)->status(), "Ruta {$metodo} {$url} debió ser 403 para {$usuario->email}");
            }
        }

        // Cocinero, Admin y Socio autorizados
        foreach ([$this->cocinero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/insumos')->assertStatus(200);
            $this->getJson('/api/insumos/alertas')->assertStatus(200);
            $this->getJson("/api/insumos/{$this->insumo->id}")->assertStatus(200);
        }
    }

    /**
     * 12. Módulo Inventario - Requisiciones Creación (role:cocinero|cocinera|administrador|socio).
     */
    public function test_modulo_requisiciones_creacion_protege_401_y_403(): void
    {
        // Sin token -> 401
        $this->postJson('/api/requisiciones', [])->assertStatus(401);

        // Mesero, Cajero y Contadora -> 403
        foreach ([$this->mesero, $this->cajero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->postJson('/api/requisiciones', [])->assertStatus(403);
        }

        // Cocinero, Admin y Socio tienen autorización (no reciben 401 ni 403)
        foreach ([$this->cocinero, $this->admin, $this->socio] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->assertNotEquals(403, $this->postJson('/api/requisiciones', [])->status());
        }
    }

    /**
     * 13. Módulo Inventario - Requisiciones Gestión Bodega (role:administrador|socio).
     */
    public function test_modulo_requisiciones_gestion_bodega_protege_401_y_403(): void
    {
        $rutas = [
            ['GET',   '/api/requisiciones'],
            ['PATCH', "/api/requisiciones/{$this->requisicion->id}/resolucion"],
            ['POST',  "/api/requisiciones/{$this->requisicion->id}/transferir"],
        ];

        // Sin token -> 401
        foreach ($rutas as [$metodo, $url]) {
            $this->json($metodo, $url)->assertStatus(401);
        }

        // Cocinero, Mesero, Cajero y Contadora -> 403
        foreach ([$this->cocinero, $this->mesero, $this->cajero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            foreach ($rutas as [$metodo, $url]) {
                $this->assertEquals(403, $this->json($metodo, $url)->status());
            }
        }

        // Admin y Socio -> 200 en listado
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/requisiciones')->assertStatus(200);

        Sanctum::actingAs($this->socio);
        $this->getJson('/api/requisiciones')->assertStatus(200);
    }

    /**
     * 14. Módulo Reportes - Dashboard Gerencial (role:socio|administrador).
     */
    public function test_modulo_reportes_dashboard_gerencial_protege_401_y_403(): void
    {
        $url = '/api/reportes/dashboard';

        // Sin token -> 401
        $this->getJson($url)->assertStatus(401);

        // Cajero, Mesero, Cocinero y Contadora -> 403
        foreach ([$this->cajero, $this->mesero, $this->cocinero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson($url)->assertStatus(403);
        }

        // Socio y Administrador -> 200
        Sanctum::actingAs($this->socio);
        $this->getJson($url)->assertStatus(200);

        Sanctum::actingAs($this->admin);
        $this->getJson($url)->assertStatus(200);
    }

    /**
     * 15. Módulo Reportes - Exportación de Reportes (role:contadora|socio|administrador).
     */
    public function test_modulo_reportes_exportacion_protege_401_y_403(): void
    {
        $url = '/api/reportes/exportar?fecha_desde=2026-10-01&fecha_hasta=2026-10-25&formato=csv';

        // Sin token -> 401
        $this->getJson($url)->assertStatus(401);

        // Cajero, Mesero y Cocinero -> 403
        foreach ([$this->cajero, $this->mesero, $this->cocinero] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson($url)->assertStatus(403);
        }

        // Contadora, Socio y Administrador -> 200
        foreach ([$this->contadora, $this->socio, $this->admin] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson($url)->assertStatus(200);
        }
    }

    /**
     * 16. Formato estricto del Contrato de API para respuestas 401 y 403.
     */
    public function test_contrato_api_estructura_estandar_para_401_y_403(): void
    {
        // 401 sin autenticación
        $resp401 = $this->getJson('/api/usuarios');
        $resp401->assertStatus(401)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
            ])
            ->assertJson([
                'status' => 'error',
                'message' => 'No autenticado. Token ausente o inválido.',
                'data' => null,
            ]);

        // 403 con usuario autenticado pero rol insuficiente
        Sanctum::actingAs($this->mesero);
        $resp403 = $this->getJson('/api/usuarios');
        $resp403->assertStatus(403)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
            ])
            ->assertJson([
                'status' => 'error',
                'message' => 'No tienes permisos suficientes para realizar esta acción.',
                'data' => null,
            ]);
    }
}
