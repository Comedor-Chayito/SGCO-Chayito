<?php

namespace App\Services;

use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ComandaService
{
    /**
     * Registra una nueva comanda en estado "pendiente" dentro de una transacción.
     * Calcula el subtotal como suma de (cantidad × precio_unitario) de cada ítem.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  RegistrarComandaRequest $request   Datos validados de la solicitud.
     * @param  int                    $usuarioId  ID del usuario que registra la comanda.
     * @return Comanda La comanda creada con sus detalles cargados.
     *
     * @throws \Throwable Si falla la transacción de base de datos.
     */
    public function registrar(RegistrarComandaRequest $request, int $usuarioId): Comanda
    {
        return DB::transaction(function () use ($request, $usuarioId) {
            $items     = $request->validated()['items'];
            $subtotal  = $this->calcularSubtotal($items);

            $comanda = Comanda::create([
                'mesa_id'       => $request->validated()['mesa_id'] ?? null,
                'usuario_id'    => $usuarioId,
                'canal'         => $request->validated()['canal'],
                'estado'        => 'pendiente',
                'observaciones' => $request->validated()['observaciones'] ?? null,
                'subtotal'      => $subtotal,
            ]);

            foreach ($items as $item) {
                $platillo = Platillo::findOrFail($item['platillo_id']);

                $comanda->detalles()->create([
                    'platillo_id'    => $platillo->id,
                    'cantidad'       => $item['cantidad'],
                    'precio_unitario' => $platillo->precio_unitario,
                    'observaciones'  => $item['observaciones'] ?? null,
                ]);
            }

            return $comanda->load('detalles.platillo', 'mesa');
        });
    }

    /**
     * Retorna todos los platillos marcados como disponibles en el menú del día.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
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
     * Retorna todas las mesas del comedor con su estado actual.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @return Collection<int, Mesa>
     */
    public function listarMesas(): Collection
    {
        return Mesa::orderBy('numero')
            ->get(['id', 'numero', 'capacidad', 'estado']);
    }

    /**
     * Retorna las comandas activas para la cola de cocina en orden de llegada (FIFO).
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  string|null $filtroEstado Estado opcional para filtrar ('pendiente', 'en_cocina').
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
            $query->whereIn('estado', ['pendiente', 'en_cocina']);
        }

        return $query->get();
    }

    /**
     * Actualiza el estado operativo de una comanda.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  Comanda $comanda     Instancia de la comanda a modificar.
     * @param  string  $nuevoEstado Nuevo valor para el campo estado.
     * @return Comanda Comanda actualizada con relaciones cargadas.
     */
    public function actualizarEstado(Comanda $comanda, string $nuevoEstado): Comanda
    {
        $comanda->update(['estado' => $nuevoEstado]);

        return $comanda->load('detalles.platillo', 'mesa', 'usuario');
    }

    /**
     * Retorna las comandas completadas recientemente (últimas 20) ordenadas por fecha de despacho.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – US-POS-02 / RF-POS-001
     *
     * @param  int $limite Cantidad máxima de comandas a retornar.
     * @return Collection<int, Comanda>
     */
    public function listarCompletadasRecientes(int $limite = 20): Collection
    {
        return Comanda::with(['detalles.platillo', 'mesa', 'usuario'])
            ->where('estado', 'pagada')
            ->orderBy('updated_at', 'desc')
            ->take($limite)
            ->get();
    }

    /**
     * Calcula el subtotal de una comanda sumando cantidad × precio_unitario de cada ítem.
     * Obtiene el precio directamente del platillo en BD, no del payload del cliente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  array<int, array{platillo_id: int, cantidad: int}> $items Ítems validados.
     * @return float Subtotal redondeado a 2 decimales.
     */
    private function calcularSubtotal(array $items): float
    {
        $platilloIds  = array_column($items, 'platillo_id');
        $precios      = Platillo::whereIn('id', $platilloIds)
            ->pluck('precio_unitario', 'id');

        $subtotal = 0.0;

        foreach ($items as $item) {
            $precio    = (float) ($precios[$item['platillo_id']] ?? 0);
            $subtotal += $precio * $item['cantidad'];
        }

        return round($subtotal, 2);
    }

    /**
     * Retorna el listado de comandas para pantalla de caja y cola de cocina.
     * Permite filtrar por estado (ej. "pendiente", "en_cocina") y carga relaciones.
     *
     * @autor  Equipo SGCO-Chayito
     * @fecha  2026-09-19
     * @módulo POS – RF-POS-001
     *
     * @param  string|null $estado Filtro opcional por estado de comanda.
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
