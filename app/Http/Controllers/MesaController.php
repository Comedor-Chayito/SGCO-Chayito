<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarMesaRequest;
use App\Http\Requests\CrearMesaRequest;
use App\Models\Mesa;
use App\Services\MesaService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador API del catálogo de mesas para el panel de configuración (US-ADM-03 / CC-107).
 *
 * @autor  manuelmv15
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – US-ADM-03 / CC-107
 */
class MesaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de mesas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  MesaService  $mesaService  Servicio del catálogo de mesas.
     */
    public function __construct(private readonly MesaService $mesaService) {}

    /**
     * Lista todas las mesas, incluidas las inactivas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return JsonResponse Mesas ordenadas por número.
     */
    public function todas(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Catálogo completo de mesas obtenido.',
            'data' => $this->mesaService->listarTodas(),
        ]);
    }

    /**
     * Registra una mesa nueva.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  CrearMesaRequest  $request  Datos validados.
     * @return JsonResponse Mesa creada, con código 201.
     */
    public function store(CrearMesaRequest $request): JsonResponse
    {
        $mesa = $this->mesaService->crear($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Mesa registrada correctamente.',
            'data' => $mesa,
        ], 201);
    }

    /**
     * Edita el número o la capacidad de una mesa.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  ActualizarMesaRequest  $request  Datos validados.
     * @param  Mesa  $mesa  Mesa a editar.
     * @return JsonResponse Mesa actualizada.
     */
    public function update(ActualizarMesaRequest $request, Mesa $mesa): JsonResponse
    {
        $mesaActualizada = $this->mesaService->actualizar($mesa, $request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Mesa actualizada correctamente.',
            'data' => $mesaActualizada,
        ]);
    }

    /**
     * Borra una mesa sin comandas o la desactiva si tiene comandas históricas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a retirar.
     * @return JsonResponse null si se borró, o la mesa desactivada.
     */
    public function destroy(Mesa $mesa): JsonResponse
    {
        $mesaDesactivada = $this->mesaService->eliminar($mesa);

        $mensaje = $mesaDesactivada === null
            ? 'Mesa eliminada correctamente.'
            : 'La mesa tiene comandas registradas; se desactivó en lugar de eliminarse.';

        return response()->json([
            'status' => 'ok',
            'message' => $mensaje,
            'data' => $mesaDesactivada,
        ]);
    }

    /**
     * Activa o desactiva una mesa.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a alternar.
     * @return JsonResponse Mesa con su nuevo estado.
     */
    public function toggleActivo(Mesa $mesa): JsonResponse
    {
        $mesaActualizada = $this->mesaService->alternarActivo($mesa);

        $mensaje = $mesaActualizada->activa
            ? 'Mesa reactivada correctamente.'
            : 'Mesa desactivada correctamente.';

        return response()->json([
            'status' => 'ok',
            'message' => $mensaje,
            'data' => $mesaActualizada,
        ]);
    }
}
