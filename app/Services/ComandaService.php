<?php

namespace App\Services;

<<<<<<< HEAD
use App\Models\Comanda;
use App\Models\Platillo;
=======
use App\Http\Requests\RegistrarComandaRequest;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Platillo;
use Illuminate\Database\Eloquent\Collection;
>>>>>>> origin/cesar/dev
use Illuminate\Support\Facades\DB;

class ComandaService
{
    /**
<<<<<<< HEAD
     * Registra una comanda junto con su detalle de platillos dentro de una transacción.
     * El precio de cada renglón se toma del platillo en base de datos, nunca del valor
     * enviado por el cliente, y el subtotal se calcula como la suma de cantidad × precio.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  array $datos Datos validados: mesa_id, usuario_id, canal, observaciones, detalles[].
     * @return Comanda Comanda creada con su detalle, mesa y usuario cargados.
     */
    public function registrar(array $datos): Comanda
    {
        return DB::transaction(function () use ($datos) {
            $comanda = Comanda::create([
                'mesa_id' => $datos['mesa_id'] ?? null,
                'usuario_id' => $datos['usuario_id'],
                'canal' => $datos['canal'],
                'estado' => 'pendiente',
                'observaciones' => $datos['observaciones'] ?? null,
                'subtotal' => 0,
            ]);

            $renglones = [];

            foreach ($datos['detalles'] as $detalle) {
                $platillo = Platillo::findOrFail($detalle['platillo_id']);

                $comanda->detalles()->create([
                    'platillo_id' => $platillo->id,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $platillo->precio_unitario,
                    'observaciones' => $detalle['observaciones'] ?? null,
                ]);

                $renglones[] = [
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $platillo->precio_unitario,
                ];
            }

            $comanda->update(['subtotal' => $this->calcularSubtotal($renglones)]);

            return $comanda->load('detalles.platillo', 'mesa', 'usuario');
=======
     * Registra una nueva comanda en estado "pendiente" dentro de una transacción.
     * Calcula el subtotal como suma de (cantidad × precio_unitario) de cada ítem.
     *
     * @autor  Equipo SGCO-Chayito
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
>>>>>>> origin/cesar/dev
        });
    }

    /**
<<<<<<< HEAD
     * Calcula el subtotal de una comanda como la suma de cantidad × precio unitario
     * de cada renglón. No accede a base de datos: recibe los valores ya resueltos.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  array $renglones Lista de renglones, cada uno con 'cantidad' y 'precio_unitario'.
     * @return float Subtotal redondeado a 2 decimales.
     */
    public function calcularSubtotal(array $renglones): float
    {
        $subtotal = 0;

        foreach ($renglones as $renglon) {
            $subtotal += $renglon['cantidad'] * $renglon['precio_unitario'];
=======
     * Retorna todos los platillos marcados como disponibles en el menú del día.
     *
     * @autor  Equipo SGCO-Chayito
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
     * @autor  Equipo SGCO-Chayito
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
     * Calcula el subtotal de una comanda sumando cantidad × precio_unitario de cada ítem.
     * Obtiene el precio directamente del platillo en BD, no del payload del cliente.
     *
     * @autor  Equipo SGCO-Chayito
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
>>>>>>> origin/cesar/dev
        }

        return round($subtotal, 2);
    }
<<<<<<< HEAD
=======

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
>>>>>>> origin/cesar/dev
}
