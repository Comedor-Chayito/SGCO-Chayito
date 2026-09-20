<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarEstadoComandaRequest;
use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Comanda;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComandaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de comandas inyectado.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  ComandaService $comandaService Servicio de lógica de negocio de comandas.
     */
    public function __construct(private readonly ComandaService $comandaService)
    {
    }

    /**
     * Devuelve la lista de platillos disponibles en el menú del día.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return JsonResponse Lista de platillos con id, nombre, descripción y precio.
     */
    public function platillos(): JsonResponse
    {
        $platillos = $this->comandaService->listarPlatillosDisponibles();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Platillos disponibles obtenidos.',
            'data'    => $platillos,
        ]);
    }

    /**
     * Devuelve la lista de mesas del comedor con su estado actual.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return JsonResponse Lista de mesas con id, número, capacidad y estado.
     */
    public function mesas(): JsonResponse
    {
        $mesas = $this->comandaService->listarMesas();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Mesas obtenidas.',
            'data'    => $mesas,
        ]);
    }

    /**
     * Registra una nueva comanda en estado "pendiente".
     * Valida la entrada, delega la lógica al servicio y devuelve la comanda creada.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  RegistrarComandaRequest $request Datos validados de la comanda.
     * @return JsonResponse La comanda creada con sus detalles.
     */
    public function registrar(RegistrarComandaRequest $request): JsonResponse
    {
        // TODO US-ADM-02: reemplazar 1 por auth()->id() cuando Sanctum esté listo
        $usuarioId = 1;

        $comanda = $this->comandaService->registrar($request, $usuarioId);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Comanda registrada correctamente.',
            'data'    => $comanda,
        ], 201);
    }

    /**
     * Devuelve la lista de comandas registradas, opcionalmente filtradas por estado.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @param  Request $request Petición HTTP con posible parámetro ?estado=
     * @return JsonResponse Lista de comandas con sus detalles, platillos y mesa.
     */
    public function index(Request $request): JsonResponse
    {
        $estado = $request->query('estado');
        $comandas = $this->comandaService->listarComandas($estado);

        return response()->json([
            'status'  => 'ok',
            'message' => 'Comandas obtenidas.',
            'data'    => $comandas,
        ]);
    }

    /**
     * Retorna la lista de comandas activas para la pantalla de cocina en orden FIFO.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  Request $request Solicitud con filtro de estado opcional.
     * @return JsonResponse Lista de comandas activas para cocina.
     */
    public function colaCocina(Request $request): JsonResponse
    {
        $estado = $request->query('estado');

        if ($estado === 'pagada') {
            $completadas = $this->comandaService->listarCompletadasRecientes();

            return response()->json([
                'status'  => 'ok',
                'message' => 'Comandas completadas obtenidas.',
                'data'    => $completadas,
            ]);
        }

        $activas = $this->comandaService->listarColaCocina($estado);
        $completadas = $this->comandaService->listarCompletadasRecientes();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Cola de cocina obtenida.',
            'data'    => [
                'activas'     => $activas,
                'completadas' => $completadas,
            ],
        ]);
    }

    /**
     * Actualiza el estado de una comanda específica.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  ActualizarEstadoComandaRequest $request Datos validados con el nuevo estado.
     * @param  Comanda                       $comanda Instancia del modelo a actualizar.
     * @return JsonResponse Comanda actualizada con relaciones.
     */
    public function actualizarEstado(ActualizarEstadoComandaRequest $request, Comanda $comanda): JsonResponse
    {
        $comandaActualizada = $this->comandaService->actualizarEstado(
            $comanda,
            $request->validated()['estado']
        );

        return response()->json([
            'status'  => 'ok',
            'message' => 'Estado de la comanda actualizado correctamente.',
            'data'    => $comandaActualizada,
        ]);
    }
}
