<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\Insumo;
use Illuminate\Support\Facades\DB;

/**
 * Servicio único para calcular y descontar el consumo de recetas al pagar una comanda.
 *
 * @autor  Alexis
 *
 * @fecha  2026-10-19
 *
 * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
 */
class DeduccionInventarioService
{
    /**
     * Inicializa el servicio con el cálculo de cantidades definido por las recetas.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48 / CC-49
     *
     * @param  RecetaService  $recetaService  Servicio de cálculo y conversión de recetas.
     */
    public function __construct(private readonly RecetaService $recetaService) {}

    /**
     * Calcula el consumo por receta de una comanda y lo consolida por insumo.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-48
     *
     * @param  Comanda  $comanda  Comanda cuyos platillos se usarán para calcular el consumo.
     * @return array<int, array{
     *     insumo_id: int,
     *     nombre: string,
     *     unidad_medida: string,
     *     total_a_descontar: float,
     *     desglose: array<int, array{
     *         platillo_id: int,
     *         platillo_nombre: string,
     *         unidades_vendidas: int,
     *         porcion_en_unidad_bodega: float,
     *         subtotal: float
     *     }>
     * }>
     */
    public function calcularConsumosPorComanda(Comanda $comanda): array
    {
        $comanda->loadMissing('detalles.platillo.recetas.insumo');
        $consumos = [];

        foreach ($comanda->detalles as $detalle) {
            foreach ($detalle->platillo->recetas as $receta) {
                $insumo = $receta->insumo;

                if (! $insumo) {
                    continue;
                }

                $descuento = $this->recetaService->descuentoEnBase($receta, $detalle->cantidad);

                if (! isset($consumos[$insumo->id])) {
                    $consumos[$insumo->id] = [
                        'insumo_id' => $insumo->id,
                        'nombre' => $insumo->nombre,
                        'unidad_medida' => $insumo->unidad_medida,
                        'total_a_descontar' => 0.0,
                        'desglose' => [],
                    ];
                }

                $consumos[$insumo->id]['total_a_descontar'] = round(
                    $consumos[$insumo->id]['total_a_descontar'] + $descuento,
                    6
                );

                $consumos[$insumo->id]['desglose'][] = [
                    'platillo_id' => $detalle->platillo->id,
                    'platillo_nombre' => $detalle->platillo->nombre,
                    'unidades_vendidas' => $detalle->cantidad,
                    'porcion_en_unidad_bodega' => round(
                        $this->recetaService->descuentoEnBase($receta, 1),
                        6
                    ),
                    'subtotal' => $descuento,
                ];
            }
        }

        return array_values($consumos);
    }

    /**
     * Descuenta del stock los insumos requeridos por los detalles de una comanda pagada.
     * Agrupa los insumos repetidos y bloquea sus filas para mantener el inventario consistente.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47 / CC-49
     *
     * @param  Comanda  $comanda  Comanda cuyos platillos ya fueron cobrados.
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
     */
    public function descontarPorComanda(Comanda $comanda): array
    {
        $consumos = $this->calcularConsumosPorComanda($comanda);

        if ($consumos === []) {
            return [];
        }

        return DB::transaction(function () use ($consumos) {
            $insumos = Insumo::query()
                ->whereIn('id', collect($consumos)->pluck('insumo_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $descontados = [];

            foreach ($consumos as $consumo) {
                $insumo = $insumos->get($consumo['insumo_id']);

                if (! $insumo) {
                    continue;
                }

                $stockAnterior = (float) $insumo->stock_actual;
                $stockActual = round($stockAnterior - $consumo['total_a_descontar'], 6);

                $insumo->update(['stock_actual' => $stockActual]);

                $descontados[] = [
                    'insumo_id' => $insumo->id,
                    'nombre' => $insumo->nombre,
                    'unidad_medida' => $insumo->unidad_medida,
                    'stock_anterior' => $stockAnterior,
                    'cantidad_descontada' => $consumo['total_a_descontar'],
                    'stock_actual' => $stockActual,
                    'stock_minimo' => (float) $insumo->stock_minimo,
                    'alerta_stock_bajo' => $stockActual <= (float) $insumo->stock_minimo,
                ];
            }

            return $descontados;
        });
    }
}
