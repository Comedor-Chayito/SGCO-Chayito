<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarPermisosRolRequest;
use App\Services\MatrizPermisoService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

/**
 * Controlador API para consultar y actualizar la matriz rol × permiso (US-ADM-04 / CC-121 / CC-122).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-16
 * @módulo Administración y Seguridad – RF-ADM-008
 */
class MatrizPermisoController extends Controller
{
    public function __construct(private readonly MatrizPermisoService $matrizPermisoService)
    {
    }

    /**
     * Retorna la matriz bidimensional de roles × permisos junto con módulos y metadatos.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $matriz = $this->matrizPermisoService->obtenerMatriz();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Matriz de roles y permisos obtenida correctamente.',
            'data'    => $matriz,
        ]);
    }

    /**
     * Actualiza los permisos de un rol y registra la bitácora de auditoría (US-ADM-04 / CC-122).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-11-18
     * @módulo Administración y Seguridad – RF-ADM-008 / CC-122
     *
     * @param  ActualizarPermisosRolRequest $request Formulario validado.
     * @param  string                       $rol     Nombre o identificador numérico del rol.
     * @return JsonResponse
     */
    public function actualizarPermisos(ActualizarPermisosRolRequest $request, string $rol): JsonResponse
    {
        $rolModel = is_numeric($rol)
            ? Role::where('id', (int) $rol)->first()
            : Role::where('name', $rol)->first();

        if (! $rolModel) {
            return response()->json([
                'status'  => 'error',
                'message' => "El rol especificado '{$rol}' no existe en el sistema.",
                'data'    => null,
            ], 404);
        }

        $resultado = $this->matrizPermisoService->actualizarPermisosRol(
            $rolModel,
            $request->validated()['permisos']
        );

        return response()->json([
            'status'  => 'ok',
            'message' => "Permisos del rol '{$rolModel->name}' actualizados correctamente.",
            'data'    => $resultado,
        ], 200);
    }
}
