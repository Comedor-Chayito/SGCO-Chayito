<?php

namespace App\Services;

use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComandaService
{
    /** Monto máximo que admite comandas.subtotal DECIMAL(10,2). */
    public const MONTO_MAXIMO_COMANDA = 99999999.99;

    /**
     * Inicializa el servicio de comandas con la verificación del estado de la caja.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-18
     *
     * @módulo POS – RF-POS-001 / US-POS-04
     *
     * @param  EstadoCajaService  $estadoCajaService  Servicio que valida apertura y cierre de caja.
     */
    public function __construct(private readonly EstadoCajaService $estadoCajaService) {}

    /**
     * Registra una nueva comanda en estado "pendiente" dentro de una transacción.
     * Calcula el subtotal como suma de (cantidad × precio_unitario) de cada ítem.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-18
     *
     * @módulo POS – RF-POS-001
     *
     * @param  RegistrarComandaRequest  $request  Datos validados de la solicitud.
     * @param  int  $usuarioId  ID del usuario que registra la comanda.
     * @return Comanda La comanda creada con sus detalles cargados.
     *
     * @throws ValidationException Si la caja del día ya fue cerrada.
     * @throws \Throwable Si falla la transacción de base de datos.
     */
    public function registrar(RegistrarComandaRequest $request, int $usuarioId): Comanda
    {
        // Se admiten comandas antes de la apertura, pero no después del cierre del día
        $this->estadoCajaService->asegurarNoCerrada();

        return DB::transaction(function () use ($request, $usuarioId) {
            $items = $request->validated()['items'];
            $platillos = $this->bloquearPlatillosDisponibles($items);
            $subtotal = $this->calcularSubtotal($items, $platillos);

            $comanda = Comanda::create([
                'mesa_id' => $request->validated()['mesa_id'] ?? null,
                'usuario_id' => $usuarioId,
                'canal' => $request->validated()['canal'],
                'estado' => 'pendiente',
                'observaciones' => $request->validated()['observaciones'] ?? null,
                'subtotal' => $subtotal,
            ]);

            // Si es comanda de mesa, marcar la mesa como ocupada (CC-34)
            if (! empty($request->validated()['mesa_id'])) {
                Mesa::where('id', $request->validated()['mesa_id'])
                    ->update(['estado' => 'ocupada']);
            }

            foreach ($items as $item) {
                $platillo = $platillos[$item['platillo_id']];

                $comanda->detalles()->create([
                    'platillo_id' => $platillo->id,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $platillo->precio_unitario,
                    'observaciones' => $item['observaciones'] ?? null,
                ]);
            }

            return $comanda->load('detalles.platillo', 'mesa');
        });
    }

    /**
     * Retorna todos los platillos marcados como disponibles en el menú del día.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-18
     *
     * @módulo POS – RF-POS-001
     *
     * @return Collection<int, Platillo>
     */
    public function listarPlatillosDisponibles(): Collection
    {
        return Platillo::where('disponible', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'precio_unitario', 'disponible']);
    }

    /**
     * Retorna las mesas activas del comedor con su estado actual.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-18
     *
     * @módulo POS – RF-POS-001
     *
     * @return Collection<int, Mesa>
     */
    public function listarMesas(): Collection
    {
        // Las mesas desactivadas desde el panel de configuración no se ofrecen en el POS (CC-107)
        return Mesa::where('activa', true)
            ->orderBy('numero')
            ->get(['id', 'numero', 'capacidad', 'estado']);
    }

    /**
     * Retorna las comandas activas para la cola de cocina en orden de llegada (FIFO).
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  string|null  $filtroEstado  Estado opcional para filtrar ('pendiente', 'en_preparacion').
     * @return Collection<int, Comanda> Colección de comandas ordenadas por llegada.
     */
    public function listarColaCocina(?string $filtroEstado = null): Collection
    {
        $query = Comanda::with(['detalles.platillo', 'mesa', 'usuario'])
            ->orderBy('created_at', 'asc');

        if ($filtroEstado) {
            $query->where('estado', $filtroEstado);
        } else {
            // Por defecto en cocina se muestran las pendientes y las que están en preparación
            $query->whereIn('estado', ['pendiente', 'en_preparacion']);
        }

        return $query->get();
    }

    /**
     * Actualiza el estado operativo de una comanda.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  Comanda  $comanda  Instancia de la comanda a modificar.
     * @param  string  $nuevoEstado  Nuevo valor para el campo estado (nunca "pagada": eso lo hace el cobro).
     * @return Comanda Comanda actualizada con relaciones cargadas.
     */
    public function actualizarEstado(Comanda $comanda, string $nuevoEstado): Comanda
    {
        return DB::transaction(function () use ($comanda, $nuevoEstado) {
            $comanda = Comanda::query()->lockForUpdate()->findOrFail($comanda->id);

            if (in_array($comanda->estado, ['pagada', 'cancelada'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'No se puede modificar una comanda pagada o cancelada.',
                ]);
            }

            $comanda->update(['estado' => $nuevoEstado]);

            // Si se cancela y tiene mesa, verificar liberación de mesa (CC-34)
            if ($nuevoEstado === 'cancelada' && $comanda->mesa_id) {
                $otrasActivas = Comanda::where('mesa_id', $comanda->mesa_id)
                    ->where('id', '!=', $comanda->id)
                    ->whereIn('estado', ['pendiente', 'en_preparacion', 'lista'])
                    ->exists();

                if (! $otrasActivas && $comanda->mesa) {
                    $comanda->mesa->update(['estado' => 'libre']);
                }
            }

            return $comanda->load('detalles.platillo', 'mesa', 'usuario');
        });
    }

    /**
     * Retorna las comandas que la cocina ya terminó (listas para cobro y pagadas),
     * las más recientes primero, para que cocina sepa qué órdenes ya despachó.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  int  $limite  Cantidad máxima de comandas a retornar.
     * @return Collection<int, Comanda>
     */
    public function listarCompletadasRecientes(int $limite = 20): Collection
    {
        return Comanda::with(['detalles.platillo', 'mesa', 'usuario'])
            ->whereIn('estado', ['lista', 'pagada'])
            ->orderBy('updated_at', 'desc')
            ->take($limite)
            ->get();
    }

    /**
     * Bloquea (lockForUpdate) los platillos de la comanda y verifica que sigan disponibles.
     * Se ejecuta dentro de la transacción: una desactivación o cambio de precio concurrente
     * espera a que la comanda se registre, y la comanda usa el precio y estado bloqueados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-11-17
     *
     * @módulo POS – RF-POS-001 / US-ADM-03 (CC-106)
     *
     * @param  array<int, array{platillo_id: int, cantidad: int}>  $items  Ítems validados.
     * @return \Illuminate\Support\Collection<int, Platillo> Platillos indexados por id.
     *
     * @throws ValidationException Si un platillo ya no existe o no está disponible.
     */
    private function bloquearPlatillosDisponibles(array $items): \Illuminate\Support\Collection
    {
        $platillos = Platillo::whereIn('id', array_column($items, 'platillo_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $indice => $item) {
            $platillo = $platillos->get($item['platillo_id']);

            if ($platillo === null) {
                throw ValidationException::withMessages([
                    "items.{$indice}.platillo_id" => 'Uno de los platillos seleccionados no existe.',
                ]);
            }

            if (! $platillo->disponible) {
                throw ValidationException::withMessages([
                    "items.{$indice}.platillo_id" => 'El platillo seleccionado no está disponible en el menú del día.',
                ]);
            }

            // Si el cliente informó el precio que mostró, debe coincidir con el vigente bajo bloqueo
            $precioEsperado = $item['precio_esperado'] ?? null;
            $centavosVigentes = (int) round((float) $platillo->precio_unitario * 100);

            if ($precioEsperado !== null && (int) round((float) $precioEsperado * 100) !== $centavosVigentes) {
                throw ValidationException::withMessages([
                    "items.{$indice}.precio_esperado" => "El precio de {$platillo->nombre} cambió a "
                        . number_format((float) $platillo->precio_unitario, 2)
                        . '. Revisa la orden y vuelve a confirmarla.',
                ]);
            }
        }

        return $platillos;
    }

    /**
     * Calcula el subtotal de una comanda sumando cantidad × precio_unitario de cada ítem.
     * Toma el precio de los platillos bloqueados en BD, no del payload del cliente, y rechaza
     * totales que no caben en comandas.subtotal DECIMAL(10,2).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-09-18
     *
     * @módulo POS – RF-POS-001
     *
     * @param  array<int, array{platillo_id: int, cantidad: int}>  $items  Ítems validados.
     * @param  \Illuminate\Support\Collection<int, Platillo>  $platillos  Platillos bloqueados por id.
     * @return float Subtotal redondeado a 2 decimales.
     *
     * @throws ValidationException Si el subtotal supera MONTO_MAXIMO_COMANDA.
     */
    private function calcularSubtotal(array $items, \Illuminate\Support\Collection $platillos): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $precio = (float) $platillos[$item['platillo_id']]->precio_unitario;
            $subtotal += $precio * $item['cantidad'];
        }

        $subtotal = round($subtotal, 2);

        if ($subtotal > self::MONTO_MAXIMO_COMANDA) {
            throw ValidationException::withMessages([
                'items' => 'El total de la comanda supera el monto máximo permitido (99,999,999.99).',
            ]);
        }

        return $subtotal;
    }

    /**
     * Retorna el listado de comandas para pantalla de caja y cola de cocina.
     * Permite filtrar por estado (ej. "pendiente", "lista") y carga relaciones.
     *
     * @autor  Equipo SGCO-Chayito
     *
     * @fecha  2026-09-19
     *
     * @módulo POS – RF-POS-001
     *
     * @param  string|null  $estado  Filtro opcional por estado de comanda.
     * @return Collection<int, Comanda>
     */
    public function listarComandas(?string $estado = null): Collection
    {
        $query = Comanda::with(['detalles.platillo', 'mesa', 'usuario:id,name'])
            ->orderByDesc('id');

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->get();
    }
}
