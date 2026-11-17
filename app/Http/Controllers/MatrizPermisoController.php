<?php

namespace App\Http\Controllers;

use App\Services\MatrizPermisoService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API para consultar la matriz rol × permiso (US-ADM-04 / CC-121).
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
}
