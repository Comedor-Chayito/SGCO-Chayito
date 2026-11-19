<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearSubcategoriaEgresoRequest;
use App\Http\Requests\RenombrarSubcategoriaEgresoRequest;
use App\Models\SubcategoriaEgreso;
use App\Services\SubcategoriaEgresoService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API del catálogo de subcategorías de egreso (CC-13 / US-CON-01).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005
 */
class SubcategoriaEgresoController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de subcategorías de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005
     *
     * @param  SubcategoriaEgresoService $subcategoriaEgresoService Servicio del catálogo.
     */
    public function __construct(private readonly SubcategoriaEgresoService $subcategoriaEgresoService)
    {
    }

    /**
     * Lista las subcategorías activas para el formulario de registro de facturas.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 / CC-62
     *
     * @return JsonResponse Subcategorías activas ordenadas por nombre.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'message' => 'Subcategorías de egreso obtenidas correctamente.',
            'data'    => $this->subcategoriaEgresoService->listarActivas(),
        ]);
    }

    /**
     * Lista todas las subcategorías, incluidas las inactivas, para el panel de configuración.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @return JsonResponse Subcategorías ordenadas por nombre con su estado.
     */
    public function todas(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'message' => 'Catálogo completo de subcategorías de egreso obtenido.',
            'data'    => $this->subcategoriaEgresoService->listarTodas(),
        ]);
    }

    /**
     * Registra una subcategoría de egreso nueva.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  CrearSubcategoriaEgresoRequest $request Datos validados.
     * @return JsonResponse Subcategoría creada, con código 201.
     */
    public function store(CrearSubcategoriaEgresoRequest $request): JsonResponse
    {
        $subcategoria = $this->subcategoriaEgresoService->crear($request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Subcategoría de egreso creada correctamente.',
            'data'    => $subcategoria,
        ], 201);
    }

    /**
     * Cambia el nombre de una subcategoría de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  RenombrarSubcategoriaEgresoRequest $request            Nombre nuevo validado.
     * @param  SubcategoriaEgreso                 $subcategoriaEgreso Subcategoría a renombrar.
     * @return JsonResponse Subcategoría renombrada.
     */
    public function update(
        RenombrarSubcategoriaEgresoRequest $request,
        SubcategoriaEgreso $subcategoriaEgreso,
    ): JsonResponse {
        $subcategoria = $this->subcategoriaEgresoService->renombrar($subcategoriaEgreso, $request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Subcategoría de egreso renombrada correctamente.',
            'data'    => $subcategoria,
        ]);
    }

    /**
     * Activa o desactiva una subcategoría de egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-17
     * @módulo Administración – US-ADM-03 / CC-108
     *
     * @param  SubcategoriaEgreso $subcategoriaEgreso Subcategoría a alternar.
     * @return JsonResponse Subcategoría con su nuevo estado.
     */
    public function toggleActivo(SubcategoriaEgreso $subcategoriaEgreso): JsonResponse
    {
        $subcategoria = $this->subcategoriaEgresoService->alternarActivo($subcategoriaEgreso);

        $mensaje = $subcategoria->activo
            ? 'Subcategoría de egreso reactivada correctamente.'
            : 'Subcategoría de egreso desactivada correctamente.';

        return response()->json([
            'status'  => 'ok',
            'message' => $mensaje,
            'data'    => $subcategoria,
        ]);
    }
}
