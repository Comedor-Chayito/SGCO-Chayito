<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Http\Requests\StoreComandaRequest;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;

class ComandaController extends Controller
{
=======
use App\Http\Requests\RegistrarComandaRequest;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComandaController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de comandas inyectado.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  ComandaService $comandaService Servicio de lógica de negocio de comandas.
     */
>>>>>>> origin/cesar/dev
    public function __construct(private readonly ComandaService $comandaService)
    {
    }

    /**
<<<<<<< HEAD
     * Registra una nueva comanda con su detalle de platillos.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  StoreComandaRequest $request Datos ya validados de la comanda.
     * @return JsonResponse
     */
    public function store(StoreComandaRequest $request): JsonResponse
    {
        $comanda = $this->comandaService->registrar($request->validated());

        return response()->json([
            'status' => 'ok',
            'message' => 'Comanda registrada correctamente.',
            'data' => $comanda,
        ], 201);
    }
=======
     * Devuelve la lista de platillos disponibles en el menú del día.
     *
     * @autor  Equipo SGCO-Chayito
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
     * @autor  Equipo SGCO-Chayito
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
     * @autor  Equipo SGCO-Chayito
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
>>>>>>> origin/cesar/dev
}
