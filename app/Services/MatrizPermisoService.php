<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Servicio para consultar y consolidar la matriz de roles y permisos RBAC (US-ADM-04 / CC-121 / CC-122).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-16
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class MatrizPermisoService
{
    /**
     * Permisos administrativos críticos que no pueden ser revocados del rol administrador
     * para evitar la pérdida irreversible del acceso al sistema (CC-122 / CC-126).
     *
     * @var list<string>
     */
    public const PERMISOS_CRITICOS_ADMIN = ['usuarios.ver', 'usuarios.editar'];

    public function __construct(private readonly AuditoriaService $auditoriaService)
    {
    }
    /**
     * Mapeo de descripciones legibles para los permisos oficiales del sistema.
     *
     * @var array<string, string>
     */
    private const DESCRIPCIONES_PERMISOS = [
        'usuarios.ver'         => 'Ver listado y detalles de usuarios del sistema',
        'usuarios.crear'       => 'Registrar nuevas cuentas de usuario',
        'usuarios.editar'      => 'Modificar datos, contraseñas y roles de usuarios',
        'usuarios.desactivar'  => 'Desactivar o reactivar cuentas de usuarios',
        'comandas.ver'         => 'Visualizar comandas en sala y cola de preparación',
        'comandas.crear'       => 'Tomar y enviar nuevas comandas a cocina',
        'cobros.ver'           => 'Consultar historial de ventas y comprobantes',
        'cobros.procesar'      => 'Procesar cobros y registrar retiros de caja',
        'inventario.ver'       => 'Consultar existencias de insumos y alertas de stock',
        'inventario.gestionar' => 'Gestionar insumos, recetas y requisiciones entre áreas',
        'contabilidad.ver'     => 'Consultar egresos, facturas y desglose de IVA crédito fiscal',
        'reportes.ver'         => 'Acceder al dashboard gerencial y exportación de reportes',
    ];

    /**
     * Mapeo de etiquetas legibles para los módulos del sistema.
     *
     * @var array<string, string>
     */
    private const ETIQUETAS_MODULOS = [
        'usuarios'     => 'Administración de Usuarios',
        'comandas'     => 'Punto de Venta (Comandas)',
        'cobros'       => 'Caja y Cobros',
        'inventario'   => 'Inventario y Recetas',
        'contabilidad' => 'Contabilidad y Egresos',
        'reportes'     => 'Reportes y Finanzas',
    ];

    /**
     * Construye y retorna la matriz bidimensional completa de roles × permisos.
     *
     * @return array<string, mixed>
     */
    public function obtenerMatriz(): array
    {
        $roles = Role::with('permissions')->orderBy('id', 'asc')->get();
        $permisos = Permission::orderBy('id', 'asc')->get();

        $permisosFormateados = [];
        $modulos = [];

        foreach ($permisos as $permiso) {
            $partes = explode('.', $permiso->name, 2);
            $moduloKey = $partes[0] ?? 'general';
            $accion = $partes[1] ?? $permiso->name;

            $itemPermiso = [
                'id'          => $permiso->id,
                'name'        => $permiso->name,
                'modulo'      => $moduloKey,
                'accion'      => $accion,
                'descripcion' => self::DESCRIPCIONES_PERMISOS[$permiso->name] ?? "Permiso para {$permiso->name}",
            ];

            $permisosFormateados[] = $itemPermiso;

            if (! isset($modulos[$moduloKey])) {
                $modulos[$moduloKey] = [
                    'clave'    => $moduloKey,
                    'nombre'   => self::ETIQUETAS_MODULOS[$moduloKey] ?? ucfirst($moduloKey),
                    'permisos' => [],
                ];
            }

            $modulos[$moduloKey]['permisos'][] = $itemPermiso;
        }

        $rolesFormateados = [];
        $matriz = [];

        foreach ($roles as $rol) {
            $permisosDelRol = $rol->permissions->pluck('name')->flip()->toArray();

            $rolesFormateados[] = [
                'id'             => $rol->id,
                'name'           => $rol->name,
                'permisos'       => $rol->permissions->pluck('name')->values()->toArray(),
                'total_permisos' => $rol->permissions->count(),
            ];

            $filaRol = [];
            foreach ($permisos as $permiso) {
                $filaRol[$permiso->name] = isset($permisosDelRol[$permiso->name]);
            }
            $matriz[$rol->name] = $filaRol;
        }

        return [
            'roles'          => $rolesFormateados,
            'permisos'       => $permisosFormateados,
            'modulos'        => array_values($modulos),
            'matriz'         => $matriz,
            'total_roles'    => count($rolesFormateados),
            'total_permisos' => count($permisosFormateados),
        ];
    }

    /**
     * Actualiza los permisos asignados a un rol oficial y registra la bitácora de auditoría.
     * Sincroniza los permisos en Spatie y limpia la caché interna de roles (US-ADM-04 / CC-122).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-11-18
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-122
     *
     * @param  Role          $rol      Instancia del rol a modificar.
     * @param  array<string> $permisos Lista de nombres de permisos válidos.
     * @return array{id: int, name: string, permisos: list<string>, total_permisos: int}
     *
     * @throws ValidationException Si se intenta revocar permisos críticos del rol administrador.
     */
    public function actualizarPermisosRol(Role $rol, array $permisos): array
    {
        $permisosNormalizados = array_values(array_unique($permisos));

        // Regla de consistencia: el rol administrador debe conservar sus permisos críticos
        if ($rol->name === 'administrador') {
            foreach (self::PERMISOS_CRITICOS_ADMIN as $permisoCritico) {
                if (! in_array($permisoCritico, $permisosNormalizados, true)) {
                    throw ValidationException::withMessages([
                        'permisos' => "No es posible revocar permisos críticos ({$permisoCritico}) del rol administrador para evitar la pérdida de acceso al sistema.",
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($rol, $permisosNormalizados) {
            $rolBloqueado = Role::query()->lockForUpdate()->findOrFail($rol->id);

            $permisosAnteriores = $rolBloqueado->permissions->pluck('name')->sort()->values()->toArray();

            // Sincronizar permisos en Spatie
            $rolBloqueado->syncPermissions($permisosNormalizados);
            $rolBloqueado->load('permissions');

            $permisosNuevos = $rolBloqueado->permissions->pluck('name')->sort()->values()->toArray();

            $agregados = array_values(array_diff($permisosNuevos, $permisosAnteriores));
            $removidos = array_values(array_diff($permisosAnteriores, $permisosNuevos));

            // Registrar en la bitácora de auditoría
            $this->auditoriaService->registrar(
                accion: 'permisos_rol_actualizados',
                entidad: $rolBloqueado,
                datosAnteriores: [
                    'rol'      => $rolBloqueado->name,
                    'permisos' => $permisosAnteriores,
                    'total'    => count($permisosAnteriores),
                ],
                datosNuevos: [
                    'rol'       => $rolBloqueado->name,
                    'permisos'  => $permisosNuevos,
                    'total'     => count($permisosNuevos),
                    'agregados' => $agregados,
                    'removidos' => $removidos,
                ]
            );

            // Olvidar la caché de permisos de Spatie para reflejar cambios inmediatamente
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'id'             => $rolBloqueado->id,
                'name'           => $rolBloqueado->name,
                'permisos'       => $permisosNuevos,
                'total_permisos' => count($permisosNuevos),
            ];
        });
    }
}
