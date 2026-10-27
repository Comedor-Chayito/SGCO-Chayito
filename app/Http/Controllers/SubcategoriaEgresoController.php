<?php

namespace App\Http\Controllers;

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
}
