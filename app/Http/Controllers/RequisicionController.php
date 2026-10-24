<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearRequisicionRequest;
use App\Http\Requests\ResolverRequisicionRequest;
use App\Models\Requisicion;
use App\Services\RequisicionService;
use Illuminate\Http\JsonResponse;

/**
 * Atiende solicitudes internas de insumos realizadas desde áreas operativas.
 *
 * @autor  Jeferson De La Cruz / Alexis
 *
 * @fecha  2026-10-22
 *
 * @módulo Inventario – RF-INV-004 / CC-52 / CC-53 / CC-54
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

    /**
     * Devuelve las requisiciones necesarias para que bodega las gestione.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
     *
     * @return JsonResponse Requisiciones con detalles, insumos y responsables.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Requisiciones obtenidas correctamente.',
            'data' => $this->requisicionService->listar(),
        ]);
    }

    /**
     * Aprueba cantidades parciales o rechaza una requisición pendiente.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-53
     *
     * @param  ResolverRequisicionRequest  $request  Resolución validada por un administrador.
     * @param  Requisicion  $requisicion  Requisición que se desea resolver.
     * @return JsonResponse Requisición aprobada o rechazada.
     */
    public function resolver(ResolverRequisicionRequest $request, Requisicion $requisicion): JsonResponse
    {
        $requisicionResuelta = $this->requisicionService->resolver(
            $requisicion,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'status' => 'ok',
            'message' => $requisicionResuelta->estado === 'aprobada'
                ? 'Requisición aprobada correctamente.'
                : 'Requisición rechazada correctamente.',
            'data' => $requisicionResuelta,
        ]);
    }

    /**
     * Mueve los insumos aprobados desde bodega general al área solicitante.
     *
     * @autor  Alexis
     * @fecha  2026-10-22
     * @módulo Inventario – RF-INV-004 / CC-54
     *
     * @param  Requisicion  $requisicion  Requisición aprobada que se transferirá.
     * @return JsonResponse Requisición transferida y stock actualizado.
     */
    public function transferir(Requisicion $requisicion): JsonResponse
    {
        $requisicionTransferida = $this->requisicionService->transferir($requisicion);

        return response()->json([
            'status' => 'ok',
            'message' => 'Stock transferido al área operativa correctamente.',
            'data' => $requisicionTransferida,
        ]);
    }
}
