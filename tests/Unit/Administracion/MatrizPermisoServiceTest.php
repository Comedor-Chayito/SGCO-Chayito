<?php

namespace Tests\Unit\Administracion;

use App\Models\Auditoria;
use App\Models\User;
use App\Services\MatrizPermisoService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pruebas unitarias del servicio de la matriz de roles y permisos RBAC (Cuadrante 1 – CC-143).
 *
 * Caja negra: 4 actualizaciones de permisos sobre distintos roles.
 * Caja blanca: 3 ejecuciones sobre permisos críticos del administrador, bitácora y armado de la matriz.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-27
 * @módulo Administración – RF-ADM-008 / US-ADM-04 / CC-122 / CC-143
 */
class MatrizPermisoServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatrizPermisoService $servicio;

    /**
     * Siembra roles y permisos oficiales y autentica al administrador, actor de la bitácora.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::where('email', 'admin@sgco-chayito.local')->firstOrFail());
        $this->servicio = app(MatrizPermisoService::class);
    }

    /**
     * Casos de caja negra: rol, permisos enviados y permisos resultantes (ordenados, sin duplicados).
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function casosCajaNegra(): array
    {
        return [
            'cn-mat-01 cajero con un permiso' => ['cajero', ['cobros.ver'], ['cobros.ver']],
            'cn-mat-02 mesero con dos permisos' => [
                'mesero',
                ['comandas.ver', 'comandas.crear'],
                ['comandas.crear', 'comandas.ver'],
            ],
            'cn-mat-03 contadora sin permisos' => ['contadora', [], []],
            'cn-mat-04 socio con duplicados' => [
                'socio',
                ['reportes.ver', 'reportes.ver', 'contabilidad.ver'],
                ['contabilidad.ver', 'reportes.ver'],
            ],
        ];
    }

    /**
     * Verifica que el rol quede exactamente con los permisos enviados y el total coincida.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @param  string       $nombreRol Rol a modificar.
     * @param  list<string> $permisos  Permisos enviados.
     * @param  list<string> $esperados Permisos resultantes esperados.
     * @return void
     */
    #[DataProvider('casosCajaNegra')]
    public function test_caja_negra_actualiza_permisos_del_rol(
        string $nombreRol,
        array $permisos,
        array $esperados
    ): void {
        $rol = Role::findByName($nombreRol);

        $resultado = $this->servicio->actualizarPermisosRol($rol, $permisos);

        $this->assertSame($esperados, $resultado['permisos']);
        $this->assertSame(count($esperados), $resultado['total_permisos']);
        $this->assertSame($esperados, $rol->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    /**
     * CB-MAT-01: rama de permisos críticos; al administrador no se le puede quitar usuarios.editar.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-126 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_no_revoca_permiso_critico_del_administrador(): void
    {
        $administrador = Role::findByName('administrador');
        $antes = $administrador->permissions->pluck('name')->sort()->values()->all();

        try {
            $this->servicio->actualizarPermisosRol($administrador, ['usuarios.ver']);
            $this->fail('Se esperaba ValidationException por permiso crítico.');
        } catch (ValidationException $excepcion) {
            $this->assertStringContainsString('usuarios.editar', $excepcion->errors()['permisos'][0]);
        }

        $this->assertSame($antes, $administrador->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    /**
     * CB-MAT-02: rama de bitácora; la auditoría guarda permisos anteriores, agregados y removidos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_registra_agregados_y_removidos_en_bitacora(): void
    {
        $cocinero = Role::findByName('cocinero');
        $cocinero->syncPermissions(['inventario.ver']);

        $this->servicio->actualizarPermisosRol($cocinero, ['comandas.ver']);

        $auditoria = Auditoria::where('accion', 'permisos_rol_actualizados')->sole();
        $this->assertSame(['inventario.ver'], $auditoria->datos_anteriores['permisos']);
        $this->assertSame(['comandas.ver'], $auditoria->datos_nuevos['agregados']);
        $this->assertSame(['inventario.ver'], $auditoria->datos_nuevos['removidos']);
    }

    /**
     * CB-MAT-03: rama de armado de la matriz; cada celda refleja si el rol tiene el permiso
     * y un permiso fuera del catálogo usa la descripción y etiqueta genéricas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-11-27
     * @módulo Administración – RF-ADM-008 / CC-143
     *
     * @return void
     */
    public function test_caja_blanca_matriz_refleja_permisos_y_etiquetas_genericas(): void
    {
        Permission::create(['name' => 'extra.prueba', 'guard_name' => 'web']);

        $matriz = $this->servicio->obtenerMatriz();

        foreach (Role::with('permissions')->get() as $rol) {
            foreach (Permission::all() as $permiso) {
                $this->assertSame(
                    $rol->permissions->contains('name', $permiso->name),
                    $matriz['matriz'][$rol->name][$permiso->name],
                    "Celda {$rol->name} × {$permiso->name} incorrecta."
                );
            }
        }

        $extra = collect($matriz['permisos'])->firstWhere('name', 'extra.prueba');
        $this->assertSame('Permiso para extra.prueba', $extra['descripcion']);
        $this->assertSame('Extra', collect($matriz['modulos'])->firstWhere('clave', 'extra')['nombre']);
        $this->assertSame(Role::count(), $matriz['total_roles']);
    }
}
