<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearRequisicionRequest;
use App\Services\RequisicionService;
use Illuminate\Http\JsonResponse;

/**
 * Atiende solicitudes internas de insumos realizadas desde áreas operativas.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52
 */
class RequisicionController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de requisiciones.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @param  RequisicionService  $requisicionService  Lógica de registro de solicitudes.
     */
    public function __construct(private readonly RequisicionService $requisicionService) {}

    /**
     * Registra una requisición pendiente sin afectar aún el stock de bodega.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-22
     *
     * @módulo Inventario – RF-INV-004 / CC-52
     *
     * @param  CrearRequisicionRequest  $request  Solicitud validada desde el formulario móvil.
     * @return JsonResponse Requisición creada con código HTTP 201.
     */
    public function store(CrearRequisicionRequest $request): JsonResponse
    {
        $requisicion = $this->requisicionService->crear(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Requisición enviada para aprobación.',
            'data' => $requisicion,
        ], 201);
    }
}
