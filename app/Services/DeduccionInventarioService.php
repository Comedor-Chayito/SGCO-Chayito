<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\Insumo;

/**
 * Servicio que descuenta el consumo de recetas cuando una comanda queda pagada.
 *
 * @autor  Alexis
 *
 * @fecha  2026-10-19
 *
 * @módulo Inventario – RF-INV-003 / CC-47
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
     * @módulo Inventario – RF-INV-003 / CC-47
     *
     * @param  RecetaService  $recetaService  Servicio de cálculo y conversión de recetas.
     */
    public function __construct(private readonly RecetaService $recetaService) {}

    /**
     * Descuenta del stock los insumos requeridos por los detalles de una comanda pagada.
     * Agrupa los insumos repetidos y bloquea sus filas para mantener el inventario consistente.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003 / CC-47
     *
     * @param  Comanda  $comanda  Comanda cuyos platillos ya fueron cobrados.
     */
    public function descontarPorComanda(Comanda $comanda): void
    {
        $comanda->loadMissing('detalles.platillo.recetas');

        $recetas = $comanda->detalles
            ->flatMap(fn ($detalle) => $detalle->platillo->recetas);

        if ($recetas->isEmpty()) {
            return;
        }

        $insumos = Insumo::query()
            ->whereIn('id', $recetas->pluck('insumo_id')->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $descuentos = [];

        foreach ($comanda->detalles as $detalle) {
            foreach ($detalle->platillo->recetas as $receta) {
                $insumo = $insumos->get($receta->insumo_id);

                if (! $insumo) {
                    continue;
                }

                $receta->setRelation('insumo', $insumo);
                $descuento = $this->recetaService->descuentoEnBase($receta, $detalle->cantidad);

                $descuentos[$insumo->id] = round(($descuentos[$insumo->id] ?? 0) + $descuento, 6);
            }
        }

        foreach ($descuentos as $insumoId => $descuento) {
            $insumo = $insumos->get($insumoId);
            $nuevoStock = round((float) $insumo->stock_actual - $descuento, 6);

            $insumo->update(['stock_actual' => $nuevoStock]);
        }
    }
}
