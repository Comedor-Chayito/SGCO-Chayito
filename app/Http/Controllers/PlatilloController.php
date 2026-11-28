<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarPlatilloRequest;
use App\Http\Requests\CambiarDisponibilidadPlatilloRequest;
use App\Http\Requests\CrearPlatilloRequest;
use App\Http\Requests\ListarPlatillosRequest;
use App\Models\Platillo;
use App\Services\PlatilloService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API del catálogo de platillos para administradores y socios (US-ADM-03).
 * El listado operativo del POS sigue en ComandaController::platillos (solo disponibles).
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – RF-ADM-008 / CC-104 / CC-106
 */
class PlatilloController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de platillos.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  PlatilloService  $platilloService  Servicio de lógica de negocio de platillos.
     */
    public function __construct(private readonly PlatilloService $platilloService) {}

    /**
     * Lista el catálogo completo de platillos, con filtros opcionales ?busqueda= y ?disponible=.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  ListarPlatillosRequest  $request  Filtros validados.
     * @return JsonResponse Lista de platillos ordenada por nombre.
     */
    public function index(ListarPlatillosRequest $request): JsonResponse
    {
        $platillos = $this->platilloService->listar($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Catálogo de platillos obtenido correctamente.',
            'data' => $platillos,
        ]);
    }

    /**
     * Registra un nuevo platillo en el catálogo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  CrearPlatilloRequest  $request  Datos validados del platillo.
     * @return JsonResponse Platillo creado con código HTTP 201.
     */
    public function store(CrearPlatilloRequest $request): JsonResponse
    {
        $platillo = $this->platilloService->crear($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Platillo registrado correctamente.',
            'data' => $platillo,
        ], 201);
    }

    /**
     * Devuelve el detalle de un platillo.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  Platillo  $platillo  Platillo resuelto por route model binding.
     * @return JsonResponse Detalle del platillo.
     */
    public function show(Platillo $platillo): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Detalle del platillo obtenido.',
            'data' => $platillo,
        ]);
    }

    /**
     * Actualiza nombre, descripción y precio de un platillo sin alterar el histórico.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  ActualizarPlatilloRequest  $request  Datos validados a actualizar.
     * @param  Platillo  $platillo  Platillo a modificar.
     * @return JsonResponse Platillo actualizado.
     */
    public function update(ActualizarPlatilloRequest $request, Platillo $platillo): JsonResponse
    {
        $platilloActualizado = $this->platilloService->actualizar($platillo, $request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Platillo actualizado correctamente.',
            'data' => $platilloActualizado,
        ]);
    }

    /**
     * Elimina un platillo sin comandas ni recetas; si tiene historial responde 422.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-104
     *
     * @param  Platillo  $platillo  Platillo a eliminar.
     * @return JsonResponse Confirmación de eliminación.
     */
    public function destroy(Platillo $platillo): JsonResponse
    {
        $this->platilloService->eliminar($platillo);

        return response()->json([
            'status' => 'ok',
            'message' => 'Platillo eliminado correctamente.',
            'data' => null,
        ]);
    }

    /**
     * Activa o desactiva un platillo del menú del día.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – RF-ADM-008 / CC-106
     *
     * @param  CambiarDisponibilidadPlatilloRequest  $request  Valor validado de disponibilidad.
     * @param  Platillo  $platillo  Platillo a modificar.
     * @return JsonResponse Platillo con la disponibilidad actualizada.
     */
    public function disponibilidad(CambiarDisponibilidadPlatilloRequest $request, Platillo $platillo): JsonResponse
    {
        $platilloActualizado = $this->platilloService->cambiarDisponibilidad(
            $platillo,
            $request->boolean('disponible')
        );

        $mensaje = $platilloActualizado->disponible
            ? 'Platillo activado en el menú del día.'
            : 'Platillo desactivado del menú del día.';

        return response()->json([
            'status' => 'ok',
            'message' => $mensaje,
            'data' => $platilloActualizado,
        ]);
    }
}
