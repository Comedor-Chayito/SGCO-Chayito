<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\ComandaDetalle;
use App\Models\Insumo;
use App\Models\Platillo;
use App\Models\Receta;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de lógica de negocio para la deducción automatizada de stock por receta estándar (US-INV-01).
 * Implementa el cálculo de porciones (CC-48) y el descuento de existencias en bodega (CC-49).
 *
 * @autor  kristiankovic
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-003
 */
class DeduccionStockService
{
    /**
     * Calcula la cantidad total de insumos a deducir según las unidades vendidas en una comanda.
     * Mapea la receta estándar de cada platillo y multiplica la porción base por las unidades.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  Comanda $comanda Comanda con sus detalles de platillos vendidos.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_actual: float,
     *     total_a_descontar: float,
     *     desglose: array<int, array{
     *         platillo_id: int,
     *         platillo_nombre: string,
     *         unidades_vendidas: int,
     *         porcion_unitaria: float,
     *         subtotal: float
     *     }>
     * }>
     */
    public function calcularPorcionesPorComanda(Comanda $comanda): array
    {
        $comanda->loadMissing(['detalles.platillo.recetas.insumo']);

        return $this->calcularPorcionesPorDetalles($comanda->detalles);
    }

    /**
     * Calcula las porciones de insumos a partir de una colección o lista de detalles de comanda.
     * Consolida las cantidades por insumo si más de un platillo requiere la misma materia prima.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  iterable<ComandaDetalle> $detalles Colección o listado de detalles de comanda.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_actual: float,
     *     total_a_descontar: float,
     *     desglose: array<int, array{
     *         platillo_id: int,
     *         platillo_nombre: string,
     *         unidades_vendidas: int,
     *         porcion_unitaria: float,
     *         subtotal: float
     *     }>
     * }>
     */
    public function calcularPorcionesPorDetalles(iterable $detalles): array
    {
        $consumoPorInsumo = [];

        foreach ($detalles as $detalle) {
            $platillo = $detalle->platillo;
            $unidadesVendidas = (int) $detalle->cantidad;

            if (! $platillo || $unidadesVendidas <= 0) {
                continue;
            }

            $recetas = $platillo->relationLoaded('recetas')
                ? $platillo->recetas
                : $platillo->recetas()->with('insumo')->get();

            foreach ($recetas as $receta) {
                $insumo = $receta->insumo;
                if (! $insumo) {
                    continue;
                }

                $insumoId = (int) $insumo->id;
                $porcionBase = (float) $receta->cantidad_por_porcion;
                $subtotalInsumo = round($porcionBase * $unidadesVendidas, 2);

                if (! isset($consumoPorInsumo[$insumoId])) {
                    $consumoPorInsumo[$insumoId] = [
                        'insumo_id'         => $insumoId,
                        'nombre'            => $insumo->nombre,
                        'unidad_medida'     => $insumo->unidad_medida,
                        'stock_actual'      => (float) $insumo->stock_actual,
                        'total_a_descontar' => 0.0,
                        'desglose'          => [],
                    ];
                }

                $consumoPorInsumo[$insumoId]['total_a_descontar'] = round(
                    $consumoPorInsumo[$insumoId]['total_a_descontar'] + $subtotalInsumo,
                    2
                );

                $consumoPorInsumo[$insumoId]['desglose'][] = [
                    'platillo_id'       => (int) $platillo->id,
                    'platillo_nombre'   => $platillo->nombre,
                    'unidades_vendidas' => $unidadesVendidas,
                    'porcion_unitaria'  => $porcionBase,
                    'subtotal'          => $subtotalInsumo,
                ];
            }
        }

        return array_values($consumoPorInsumo);
    }

    /**
     * Calcula las porciones de insumos a partir de una lista cruda de ítems (platillo_id y cantidad).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<int, array{platillo_id: int, cantidad: int}> $items Arreglo de platillos y cantidades.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_actual: float,
     *     total_a_descontar: float,
     *     desglose: array<int, array{
     *         platillo_id: int,
     *         platillo_nombre: string,
     *         unidades_vendidas: int,
     *         porcion_unitaria: float,
     *         subtotal: float
     *     }>
     * }>
     */
    public function calcularPorcionesPorItems(array $items): array
    {
        $platilloIds = array_column($items, 'platillo_id');
        $platillos = Platillo::with('recetas.insumo')
            ->whereIn('id', $platilloIds)
            ->get()
            ->keyBy('id');

        $detallesSimulados = [];

        foreach ($items as $item) {
            $platilloId = $item['platillo_id'] ?? null;
            $cantidad = (int) ($item['cantidad'] ?? 0);

            if (! $platilloId || ! isset($platillos[$platilloId]) || $cantidad <= 0) {
                continue;
            }

            $detalle = new ComandaDetalle([
                'platillo_id' => $platilloId,
                'cantidad'    => $cantidad,
            ]);
            $detalle->setRelation('platillo', $platillos[$platilloId]);

            $detallesSimulados[] = $detalle;
        }

        return $this->calcularPorcionesPorDetalles($detallesSimulados);
    }

    /**
     * Descuenta del stock centralizado de bodega las porciones calculadas para una comanda.
     * Ejecuta la operación dentro de una transacción de BD con bloqueo para consistencia concurrente.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  Comanda $comanda Comanda cuyos platillos vendidos se deducirán de bodega.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_anterior: float,
     *     cantidad_descontada: float,
     *     stock_actual: float,
     *     stock_minimo: float,
     *     alerta_stock_bajo: bool
     * }>
     *
     * @throws \Throwable Si ocurre un error durante la transacción de BD.
     */
    public function descontarStockPorComanda(Comanda $comanda): array
    {
        $consumos = $this->calcularPorcionesPorComanda($comanda);

        return $this->descontarStockPorConsumos($consumos);
    }

    /**
     * Descuenta las existencias en bodega a partir de un listado de consumos calculados.
     * Actualiza el stock_actual de cada insumo dentro de una transacción atómica.
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<int, array{insumo_id: int, total_a_descontar: float}> $consumos Lista de consumos calculados.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_anterior: float,
     *     cantidad_descontada: float,
     *     stock_actual: float,
     *     stock_minimo: float,
     *     alerta_stock_bajo: bool
     * }>
     *
     * @throws \Throwable Si ocurre un error durante la transacción de BD.
     */
    public function descontarStockPorConsumos(array $consumos): array
    {
        if (empty($consumos)) {
            return [];
        }

        return DB::transaction(function () use ($consumos) {
            $descontados = [];

            foreach ($consumos as $consumo) {
                $insumoId = (int) $consumo['insumo_id'];
                $cantidadADescontar = (float) $consumo['total_a_descontar'];

                if ($cantidadADescontar <= 0) {
                    continue;
                }

                $insumo = Insumo::lockForUpdate()->find($insumoId);
                if (! $insumo) {
                    continue;
                }

                $stockAnterior = (float) $insumo->stock_actual;
                $stockNuevo = round($stockAnterior - $cantidadADescontar, 2);

                $insumo->update([
                    'stock_actual' => $stockNuevo,
                ]);

                $descontados[] = [
                    'insumo_id'           => $insumo->id,
                    'nombre'              => $insumo->nombre,
                    'unidad_medida'       => $insumo->unidad_medida,
                    'stock_anterior'      => $stockAnterior,
                    'cantidad_descontada' => $cantidadADescontar,
                    'stock_actual'        => $stockNuevo,
                    'stock_minimo'        => (float) $insumo->stock_minimo,
                    'alerta_stock_bajo'   => $stockNuevo <= (float) $insumo->stock_minimo,
                ];
            }

            return $descontados;
        });
    }

    /**
     * Descuenta del inventario a partir de una lista cruda de ítems (platillo_id y cantidad).
     *
     * @autor  kristiankovic
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<int, array{platillo_id: int, cantidad: int}> $items Arreglo de platillos y cantidades.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     stock_anterior: float,
     *     cantidad_descontada: float,
     *     stock_actual: float,
     *     stock_minimo: float,
     *     alerta_stock_bajo: bool
     * }>
     *
     * @throws \Throwable Si ocurre un error durante la transacción de BD.
     */
    public function descontarStockPorItems(array $items): array
    {
        $consumos = $this->calcularPorcionesPorItems($items);

        return $this->descontarStockPorConsumos($consumos);
    }
}
