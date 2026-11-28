<?php

namespace Tests\Feature\Api;

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas de la bitácora de auditoría y de su relación con la matriz de permisos
 * (US-ADM-04 / RF-ADM-008 / CC-126).
 * Cubre el control de acceso, el contenido y la paginación del listado, los filtros,
 * el carácter de solo lectura, el enmascarado de datos sensibles y los eventos que
 * hoy alimentan la bitácora. Los criterios de modificación de la matriz (CC-122) quedan
 * marcados como pendientes hasta que exista el endpoint.
 *
 * @autor  Equipo SGCO-Chayito
 *
 * @fecha  2026-11-18
 *
 * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
 */
class BitacoraAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private const RUTA = '/api/auditorias';

    protected User $admin;

    protected User $socio;

    protected User $cajero;

    protected User $mesero;

    protected User $cocinero;

    protected User $contadora;

    /**
     * Siembra roles y permisos oficiales, crea un usuario por rol y deja la bitácora vacía.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@sgco-chayito.local')->first();
        $this->cajero = User::where('email', 'cajero@sgco-chayito.local')->first();
        $this->mesero = User::where('email', 'mesero@sgco-chayito.local')->first();
        $this->socio = $this->crearUsuarioConRol('socio', 'socio.bitacora@sgco-chayito.local');
        $this->cocinero = $this->crearUsuarioConRol('cocinero', 'cocinero.bitacora@sgco-chayito.local');
        $this->contadora = $this->crearUsuarioConRol('contadora', 'contadora.bitacora@sgco-chayito.local');

        // Crear usuarios y asignar roles también se audita; se limpia para contar solo lo de cada prueba
        Auditoria::query()->delete();
    }

    /**
     * Crea un usuario activo con el rol indicado.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @param  string  $rol  Nombre del rol oficial a asignar.
     * @param  string  $email  Correo único del usuario de prueba.
     * @return User Usuario persistido con el rol asignado.
     */
    private function crearUsuarioConRol(string $rol, string $email): User
    {
        $usuario = User::factory()->create(['email' => $email, 'activo' => true]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /**
     * Inserta un registro de bitácora directamente en la tabla, con fecha controlada.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @param  array  $datos  Campos a sobrescribir (accion, entidad_type, created_at, etc.).
     * @return int Identificador del registro insertado.
     */
    private function insertarRegistro(array $datos = []): int
    {
        foreach (['datos_anteriores', 'datos_nuevos'] as $campo) {
            if (isset($datos[$campo]) && is_array($datos[$campo])) {
                $datos[$campo] = json_encode($datos[$campo]);
            }
        }

        return DB::table('auditorias')->insertGetId(array_merge([
            'usuario_id' => $this->admin->id,
            'accion' => 'actualizado',
            'entidad_type' => User::class,
            'entidad_id' => $this->admin->id,
            'datos_anteriores' => null,
            'datos_nuevos' => null,
            'created_at' => '2026-11-10 12:00:00',
        ], $datos));
    }

    // ─── Control de acceso ───────────────────────────────────────────────────

    /**
     * Verifica que el administrador pueda consultar la bitácora con la estructura de la API.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_administrador_puede_consultar_la_bitacora(): void
    {
        $this->insertarRegistro();
        Sanctum::actingAs($this->admin);

        $this->getJson(self::RUTA)
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure(['status', 'message', 'data' => ['data', 'current_page', 'per_page', 'total']]);
    }

    /**
     * Verifica que el socio también pueda consultar la bitácora.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_socio_puede_consultar_la_bitacora(): void
    {
        Sanctum::actingAs($this->socio);

        $this->getJson(self::RUTA)->assertStatus(200)->assertJsonPath('status', 'ok');
    }

    /**
     * Verifica que los roles operativos reciban 403 y no vean ningún dato de la bitácora.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_roles_operativos_no_pueden_consultar_la_bitacora(): void
    {
        $this->insertarRegistro();

        foreach ([$this->cajero, $this->mesero, $this->cocinero, $this->contadora] as $usuario) {
            Sanctum::actingAs($usuario);

            $this->getJson(self::RUTA)
                ->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('data', null);
        }
    }

    /**
     * Verifica que sin token la bitácora responda 401.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_rechaza_peticiones_sin_autenticar(): void
    {
        $this->getJson(self::RUTA)->assertStatus(401)->assertJsonPath('status', 'error');
    }

    // ─── Contenido, orden y paginación ───────────────────────────────────────

    /**
     * Verifica la forma de cada registro: acción, entidad legible, usuario y datos.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_cada_registro_expone_accion_entidad_usuario_y_datos(): void
    {
        $this->insertarRegistro([
            'accion' => 'actualizado',
            'datos_anteriores' => ['name' => 'Antes'],
            'datos_nuevos' => ['name' => 'Después'],
        ]);
        Sanctum::actingAs($this->admin);

        $registro = $this->getJson(self::RUTA)->assertStatus(200)->json('data.data.0');

        $this->assertSame('actualizado', $registro['accion']);
        $this->assertSame('User', $registro['entidad']);
        $this->assertSame($this->admin->id, $registro['entidad_id']);
        $this->assertSame($this->admin->id, $registro['usuario']['id']);
        $this->assertSame($this->admin->name, $registro['usuario']['name']);
        $this->assertSame(['name' => 'Antes'], $registro['datos_anteriores']);
        $this->assertSame(['name' => 'Después'], $registro['datos_nuevos']);
        $this->assertArrayHasKey('created_at', $registro);
    }

    /**
     * Verifica que los registros salgan del más reciente al más antiguo y que, con la misma fecha,
     * el de mayor identificador vaya primero.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $this->insertarRegistro(['accion' => 'antiguo', 'created_at' => '2026-11-01 08:00:00']);
        $this->insertarRegistro(['accion' => 'empate_a', 'created_at' => '2026-11-05 08:00:00']);
        $this->insertarRegistro(['accion' => 'empate_b', 'created_at' => '2026-11-05 08:00:00']);
        $this->insertarRegistro(['accion' => 'reciente', 'created_at' => '2026-11-09 08:00:00']);
        Sanctum::actingAs($this->admin);

        $acciones = collect($this->getJson(self::RUTA)->json('data.data'))->pluck('accion')->all();

        $this->assertSame(['reciente', 'empate_b', 'empate_a', 'antiguo'], $acciones);
    }

    /**
     * Verifica la paginación por defecto de 20 registros y el paso a la segunda página.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_pagina_de_20_en_20_por_defecto(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->insertarRegistro(['accion' => "evento_{$i}"]);
        }
        Sanctum::actingAs($this->admin);

        $primera = $this->getJson(self::RUTA)->assertStatus(200);
        $this->assertCount(20, $primera->json('data.data'));
        $this->assertSame(25, $primera->json('data.total'));
        $this->assertSame(2, $primera->json('data.last_page'));

        $segunda = $this->getJson(self::RUTA.'?page=2')->assertStatus(200);
        $this->assertCount(5, $segunda->json('data.data'));
    }

    /**
     * Verifica que por_pagina se respete dentro del rango permitido.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_respeta_por_pagina(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->insertarRegistro();
        }
        Sanctum::actingAs($this->admin);

        $respuesta = $this->getJson(self::RUTA.'?por_pagina=5')->assertStatus(200);

        $this->assertCount(5, $respuesta->json('data.data'));
        $this->assertSame(5, $respuesta->json('data.per_page'));
        $this->assertSame(12, $respuesta->json('data.total'));
    }

    /**
     * Verifica que una bitácora sin registros responda 200 con lista vacía, no un error.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_vacia_responde_lista_vacia(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson(self::RUTA)
            ->assertStatus(200)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);
    }

    // ─── Filtros ─────────────────────────────────────────────────────────────

    /**
     * Verifica el filtro por usuario que realizó el cambio.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_filtra_por_usuario(): void
    {
        $this->insertarRegistro(['usuario_id' => $this->admin->id, 'accion' => 'del_admin']);
        $this->insertarRegistro(['usuario_id' => $this->socio->id, 'accion' => 'del_socio']);
        Sanctum::actingAs($this->admin);

        $acciones = collect($this->getJson(self::RUTA.'?usuario_id='.$this->socio->id)->json('data.data'))
            ->pluck('accion')->all();

        $this->assertSame(['del_socio'], $acciones);
    }

    /**
     * Verifica el filtro por tipo de acción.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_filtra_por_accion(): void
    {
        $this->insertarRegistro(['accion' => 'creado']);
        $this->insertarRegistro(['accion' => 'desactivado']);
        $this->insertarRegistro(['accion' => 'desactivado']);
        Sanctum::actingAs($this->admin);

        $respuesta = $this->getJson(self::RUTA.'?accion=desactivado')->assertStatus(200);

        $this->assertSame(2, $respuesta->json('data.total'));
        $this->assertSame(['desactivado'], collect($respuesta->json('data.data'))->pluck('accion')->unique()->all());
    }

    /**
     * Verifica que el filtro por entidad use el nombre corto de la clase, no la ruta completa.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_filtra_por_entidad_con_nombre_corto_de_clase(): void
    {
        $this->insertarRegistro(['entidad_type' => User::class, 'accion' => 'sobre_usuario']);
        $this->insertarRegistro(['entidad_type' => 'App\\Models\\Platillo', 'accion' => 'sobre_platillo']);
        Sanctum::actingAs($this->admin);

        $acciones = collect($this->getJson(self::RUTA.'?entidad=Platillo')->json('data.data'))
            ->pluck('accion')->all();

        $this->assertSame(['sobre_platillo'], $acciones);
    }

    /**
     * Verifica que el rango de fechas incluya el primer y el último segundo de ambos días límite.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_filtra_por_rango_de_fechas_con_limites_inclusivos(): void
    {
        $this->insertarRegistro(['accion' => 'antes', 'created_at' => '2026-11-04 23:59:59']);
        $this->insertarRegistro(['accion' => 'inicio', 'created_at' => '2026-11-05 00:00:00']);
        $this->insertarRegistro(['accion' => 'medio', 'created_at' => '2026-11-06 12:00:00']);
        $this->insertarRegistro(['accion' => 'fin', 'created_at' => '2026-11-07 23:59:59']);
        $this->insertarRegistro(['accion' => 'despues', 'created_at' => '2026-11-08 00:00:00']);
        Sanctum::actingAs($this->admin);

        $acciones = collect($this->getJson(self::RUTA.'?desde=2026-11-05&hasta=2026-11-07')->json('data.data'))
            ->pluck('accion')->sort()->values()->all();

        $this->assertSame(['fin', 'inicio', 'medio'], $acciones);
    }

    /**
     * Verifica que los filtros se combinen entre sí.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_combina_filtros_de_usuario_accion_y_fecha(): void
    {
        $enRango = '2026-11-06 10:00:00';

        // Solo el primero cumple usuario, acción y rango a la vez
        $this->insertarRegistro(['usuario_id' => $this->socio->id, 'accion' => 'creado', 'created_at' => $enRango]);
        $this->insertarRegistro(['usuario_id' => $this->admin->id, 'accion' => 'creado', 'created_at' => $enRango]);
        $this->insertarRegistro(['usuario_id' => $this->socio->id, 'accion' => 'eliminado', 'created_at' => $enRango]);
        $this->insertarRegistro([
            'usuario_id' => $this->socio->id,
            'accion' => 'creado',
            'created_at' => '2026-10-01 10:00:00',
        ]);
        Sanctum::actingAs($this->admin);

        $url = self::RUTA.'?usuario_id='.$this->socio->id.'&accion=creado&desde=2026-11-01&hasta=2026-11-30';
        $respuesta = $this->getJson($url)->assertStatus(200);

        $this->assertSame(1, $respuesta->json('data.total'));
    }

    /**
     * Verifica que los filtros inválidos se rechacen con 422 y el contrato de error de la API.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_filtros_invalidos_responden_422(): void
    {
        Sanctum::actingAs($this->admin);

        $casos = [
            'usuario_id=999999' => 'usuario_id',
            'usuario_id=abc' => 'usuario_id',
            'desde=05-11-2026' => 'desde',
            'hasta=2026/11/05' => 'hasta',
            'desde=2026-11-10&hasta=2026-11-01' => 'hasta',
            'por_pagina=0' => 'por_pagina',
            'por_pagina=101' => 'por_pagina',
            'por_pagina=abc' => 'por_pagina',
            'accion='.str_repeat('x', 31) => 'accion',
        ];

        foreach ($casos as $consulta => $campoConError) {
            $this->getJson(self::RUTA.'?'.$consulta)
                ->assertStatus(422)
                ->assertJsonPath('status', 'error')
                ->assertJsonValidationErrors([$campoConError]);
        }
    }

    // ─── Solo lectura ────────────────────────────────────────────────────────

    /**
     * Verifica que ningún método de escritura sobre la bitácora funcione ni altere la tabla.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_no_admite_escritura_ni_borrado_por_la_api(): void
    {
        $id = $this->insertarRegistro(['accion' => 'intocable']);
        Sanctum::actingAs($this->admin);

        $intentos = [
            ['postJson', self::RUTA],
            ['putJson', self::RUTA.'/'.$id],
            ['patchJson', self::RUTA.'/'.$id],
            ['deleteJson', self::RUTA.'/'.$id],
            ['deleteJson', self::RUTA],
        ];

        foreach ($intentos as [$metodo, $ruta]) {
            $estado = $this->{$metodo}($ruta, ['accion' => 'manipulado'])->getStatusCode();

            $this->assertContains($estado, [404, 405], "{$metodo} {$ruta} respondió {$estado}");
        }

        $this->assertSame(1, Auditoria::count());
        $this->assertDatabaseHas('auditorias', ['id' => $id, 'accion' => 'intocable']);
    }

    /**
     * Verifica a nivel de rutas que lo único registrado bajo /auditorias sean lecturas (GET/HEAD).
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_rutas_de_auditorias_solo_declaran_lectura(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_contains($ruta->uri(), 'auditorias'));

        $this->assertNotEmpty($rutas, 'No se encontró ninguna ruta de auditorías.');

        foreach ($rutas as $ruta) {
            $this->assertSame(
                [],
                array_values(array_diff($ruta->methods(), ['GET', 'HEAD'])),
                "La ruta {$ruta->uri()} admite métodos de escritura.",
            );
        }
    }

    /**
     * Verifica que consultar la matriz o la bitácora no genere registros nuevos en la bitácora.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_consultar_matriz_y_bitacora_no_genera_registros(): void
    {
        $permisosAntes = Role::findByName('administrador', 'web')->permissions()->count();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/matriz-permisos')->assertStatus(200);
        $this->getJson(self::RUTA)->assertStatus(200);

        $this->assertSame(0, Auditoria::count());
        $this->assertSame($permisosAntes, Role::findByName('administrador', 'web')->permissions()->count());
    }

    // ─── Datos sensibles ─────────────────────────────────────────────────────

    /**
     * Verifica que la contraseña de un usuario creado nunca salga en la bitácora, ni en base ni en la API.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_bitacora_no_expone_contrasenas(): void
    {
        Sanctum::actingAs($this->admin);
        $nuevo = User::factory()->create(['email' => 'nuevo.sensible@sgco-chayito.local']);

        $respuesta = $this->getJson(self::RUTA.'?accion=creado&entidad=User')->assertStatus(200);

        $registro = collect($respuesta->json('data.data'))->firstWhere('entidad_id', $nuevo->id);
        $this->assertNotNull($registro, 'No se auditó la creación del usuario.');
        $this->assertSame('[protegido]', $registro['datos_nuevos']['password']);
        $this->assertNotContains($nuevo->password, array_values($registro['datos_nuevos']));

        // También en la base: se compara contra el valor ya decodificado (el JSON crudo escapa las barras)
        $guardado = Auditoria::where('accion', 'creado')->where('entidad_id', $nuevo->id)->first();
        $this->assertSame('[protegido]', $guardado->datos_nuevos['password']);
        $this->assertNotContains($nuevo->password, array_values($guardado->datos_nuevos));
    }

    // ─── Registro automático de cambios ──────────────────────────────────────

    /**
     * Verifica que crear un usuario deje un registro con el actor autenticado como responsable.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_crear_usuario_queda_en_la_bitacora_con_su_responsable(): void
    {
        Sanctum::actingAs($this->admin);

        $nuevo = User::factory()->create(['email' => 'creado.auditado@sgco-chayito.local']);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'creado',
            'entidad_type' => User::class,
            'entidad_id' => $nuevo->id,
            'usuario_id' => $this->admin->id,
        ]);
    }

    /**
     * Verifica que desactivar un usuario desde la API quede registrado como "desactivado".
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_desactivar_usuario_queda_en_la_bitacora(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/usuarios/{$this->cajero->id}/toggle-activo")->assertStatus(200);

        $this->assertDatabaseHas('auditorias', [
            'accion' => 'desactivado',
            'entidad_type' => User::class,
            'entidad_id' => $this->cajero->id,
            'usuario_id' => $this->admin->id,
        ]);
    }

    /**
     * Verifica que asignar y quitar un rol a un usuario queden registrados con el nombre del rol.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-126)
     *
     * @return void
     */
    public function test_asignar_y_quitar_rol_queda_en_la_bitacora(): void
    {
        Sanctum::actingAs($this->admin);
        $usuario = User::factory()->create(['email' => 'rol.auditado@sgco-chayito.local', 'activo' => true]);
        Auditoria::query()->delete();

        $usuario->assignRole('cajero');
        $usuario->removeRole('cajero');

        $asignado = Auditoria::where('accion', 'rol_asignado')->where('entidad_id', $usuario->id)->first();
        $removido = Auditoria::where('accion', 'rol_removido')->where('entidad_id', $usuario->id)->first();

        $this->assertNotNull($asignado, 'No se auditó la asignación del rol.');
        $this->assertNotNull($removido, 'No se auditó la remoción del rol.');
        $this->assertContains('cajero', $asignado->datos_nuevos['roles']);
        $this->assertContains('cajero', $removido->datos_anteriores['roles']);
        $this->assertSame($this->admin->id, $asignado->usuario_id);
    }

    // ─── Modificación de la matriz de permisos auditada (CC-122) ────────────

    /**
     * Criterio: solo el administrador modifica la matriz.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-122 / CC-126)
     *
     * @return void
     */
    public function test_solo_el_administrador_puede_modificar_la_matriz(): void
    {
        $this->app['auth']->forgetGuards();

        // 1. Sin autenticación recibe 401
        $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver'],
        ])->assertStatus(401);

        // 2. Roles no administradores (socio, cajero) reciben 403
        foreach ([$this->socio, $this->cajero] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->putJson('/api/roles/cajero/permisos', [
                'permisos' => ['cobros.ver'],
            ])->assertStatus(403);
        }

        // 3. Administrador autorizado recibe 200
        Sanctum::actingAs($this->admin);
        $respuesta = $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver', 'cobros.procesar'],
        ]);

        $respuesta->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.name', 'cajero');
    }

    /**
     * Criterio: todo cambio de la matriz queda en la bitácora de auditoría.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-122 / CC-126)
     *
     * @return void
     */
    public function test_todo_cambio_de_la_matriz_queda_en_la_bitacora(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/roles/cajero/permisos', [
            'permisos' => ['cobros.ver', 'comandas.ver'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $this->admin->id,
            'accion' => 'permisos_rol_actualizados',
        ]);

        $auditoria = Auditoria::where('accion', 'permisos_rol_actualizados')->latest('id')->first();
        $this->assertNotNull($auditoria);
        $this->assertSame($this->admin->id, $auditoria->usuario_id);
        $this->assertSame('cajero', $auditoria->datos_nuevos['rol']);
        $this->assertContains('cobros.ver', $auditoria->datos_nuevos['permisos']);
    }

    /**
     * Criterio: el administrador no puede quitarse a sí mismo el acceso a la matriz ni a la administración.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-11-18
     *
     * @módulo Administración y Seguridad – RF-ADM-008 (CC-122 / CC-126)
     *
     * @return void
     */
    public function test_administrador_no_puede_quitarse_el_acceso_a_la_matriz(): void
    {
        Sanctum::actingAs($this->admin);

        // Intentar remover los permisos críticos usuarios.ver y usuarios.editar del rol administrador
        $respuesta = $this->putJson('/api/roles/administrador/permisos', [
            'permisos' => ['comandas.ver', 'cobros.ver'],
        ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['permisos']);

        // El rol administrador conserva intactos sus permisos críticos
        $rolAdmin = Role::findByName('administrador', 'web');
        $this->assertTrue($rolAdmin->hasPermissionTo('usuarios.ver'));
        $this->assertTrue($rolAdmin->hasPermissionTo('usuarios.editar'));
    }
}
