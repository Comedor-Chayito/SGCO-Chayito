<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarInsumoRequest;
use App\Http\Requests\CrearInsumoRequest;
use App\Models\Insumo;
use App\Services\InsumoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador API para el CRUD de insumos de bodega con unidad de medida (CC-45 / US-INV-01).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
class InsumoController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de insumos.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  InsumoService $insumoService Servicio de lógica de negocio de insumos.
     */
    public function __construct(private readonly InsumoService $insumoService)
    {
    }

    /**
     * Lista todos los insumos del catálogo de bodega, con búsqueda opcional por nombre.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Request $request Petición HTTP con parámetro ?busqueda= opcional.
     * @return JsonResponse Lista de insumos ordenada por nombre.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['busqueda']);
        $insumos = $this->insumoService->listar($filtros);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Insumos obtenidos correctamente.',
            'data'    => $insumos,
        ]);
    }

    /**
     * Registra un nuevo insumo en el catálogo de bodega.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  CrearInsumoRequest $request Datos validados del nuevo insumo.
     * @return JsonResponse Insumo creado con código HTTP 201.
     */
    public function store(CrearInsumoRequest $request): JsonResponse
    {
        $insumo = $this->insumoService->crear($request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Insumo registrado correctamente.',
            'data'    => $insumo,
        ], 201);
    }

    /**
     * Devuelve el detalle de un insumo específico por su ID.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo $insumo Instancia del insumo (route model binding).
     * @return JsonResponse Detalle del insumo.
     */
    public function show(Insumo $insumo): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'message' => 'Detalle del insumo obtenido.',
            'data'    => $insumo,
        ]);
    }

    /**
     * Actualiza los datos de un insumo existente (nombre, unidad de medida, stock).
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  ActualizarInsumoRequest $request Datos validados a actualizar.
     * @param  Insumo                  $insumo  Instancia del insumo a modificar.
     * @return JsonResponse Insumo actualizado.
     */
    public function update(ActualizarInsumoRequest $request, Insumo $insumo): JsonResponse
    {
        $insumoActualizado = $this->insumoService->actualizar($insumo, $request->validated());

        return response()->json([
            'status'  => 'ok',
            'message' => 'Insumo actualizado correctamente.',
            'data'    => $insumoActualizado,
        ]);
    }

    /**
     * Elimina permanentemente un insumo si no posee recetas ni requisiciones vinculadas.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo $insumo Insumo a eliminar.
     * @return JsonResponse Confirmación de eliminación.
     */
    public function destroy(Insumo $insumo): JsonResponse
    {
        $this->insumoService->eliminar($insumo);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Insumo eliminado correctamente.',
            'data'    => null,
        ]);
    }
}
